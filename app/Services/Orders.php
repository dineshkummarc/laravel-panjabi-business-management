<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Orders
{
    public function __construct(private Inventory $inventory, private Ledger $ledger) {}

    public function place(array $data, array $cart, string $key): Order
    {
        app(Modules::class)->require('orders');

        return DB::transaction(function () use ($data, $cart, $key) {
            if ($existing = Order::where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $branch = Branch::where('active', true)->findOrFail($data['branch_id']);
            if (! $cart) {
                throw ValidationException::withMessages(['cart' => 'Add at least one item.']);
            }
            $lines = [];
            $subtotal = 0;
            $cost = 0;
            foreach ($cart as $variantId => $qty) {
                $qty = (int) $qty;
                if ($qty < 1 || $qty > 1000) {
                    throw ValidationException::withMessages(['cart' => 'Quantity must be between 1 and 1,000.']);
                }
                $variant = Variant::with('product')->findOrFail($variantId);
                if (! $variant->product->active || $variant->product->kind !== 'finished') {
                    throw ValidationException::withMessages(['cart' => 'This product is unavailable.']);
                }
                $price = ($data['channel'] ?? 'web') === 'wholesale' && $variant->wholesale_price > 0 ? $variant->wholesale_price : $variant->price;
                $lines[] = ['variant_id' => $variant->id, 'quantity' => $qty, 'description' => $variant->product->name.' / '.$variant->size.' / '.$variant->color, 'unit_price' => $price, 'unit_cost' => $variant->cost];
                $subtotal += $price * $qty;
                $cost += $variant->cost * $qty;
            }
            $customer = Customer::firstOrCreate(['phone' => $data['phone']], ['name' => $data['customer_name'], 'address' => $data['address'] ?? null]);
            $shipping = ($data['channel'] ?? 'web') === 'web' ? (int) $branch->organization->delivery_fee : 0;
            $order = Order::create([
                'branch_id' => $branch->id, 'customer_id' => $customer->id, 'user_id' => auth()->id(),
                'number' => 'PB-'.strtoupper(Str::random(10)), 'token' => Str::random(48), 'idempotency_key' => $key,
                'channel' => $data['channel'] ?? 'web', 'status' => ($data['channel'] ?? 'web') === 'pos' ? 'completed' : 'pending',
                'fulfillment_status' => ($data['channel'] ?? 'web') === 'pos' ? 'delivered' : 'unfulfilled',
                'customer_name' => $data['customer_name'], 'phone' => $data['phone'], 'address' => $data['address'] ?? null,
                'subtotal' => $subtotal, 'shipping' => $shipping, 'total' => $subtotal + $shipping, 'payment_method' => $data['payment_method'] ?? 'cod',
            ]);
            foreach ($lines as $line) {
                $order->items()->create($line);
                $this->inventory->move($branch->id, $line['variant_id'], -$line['quantity'], 'sale', $order->number);
            }
            $this->ledger->post($branch->id, 'sale:'.$order->id, $order->number, [
                ['account' => 'Accounts receivable', 'debit' => $order->total, 'credit' => 0],
                ['account' => 'Sales revenue', 'debit' => 0, 'credit' => $subtotal],
                ['account' => 'Delivery revenue', 'debit' => 0, 'credit' => $shipping],
                ['account' => 'Cost of goods sold', 'debit' => $cost, 'credit' => 0],
                ['account' => 'Inventory', 'debit' => 0, 'credit' => $cost],
            ]);
            Audit::record('order.placed', $order->number, ['channel' => $order->channel, 'total' => $order->total]);

            return $order;
        });
    }

    public function pay(Order $order, int $amount, string $method, string $reference): void
    {
        DB::transaction(function () use ($order, $amount, $method, $reference) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($existing = Payment::where('reference', $reference)->first()) {
                if ($existing->order_id !== $order->id || $existing->amount !== $amount || $existing->method !== $method) {
                    throw ValidationException::withMessages(['reference' => 'This reference belongs to another payment.']);
                }

return;
            }
            $refundedValue = (int) $order->items->sum(fn ($i) => $i->returned * $i->unit_price);
            if ($order->status === 'canceled' || $amount <= 0 || $amount > $order->total - $refundedValue - $order->paid) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding balance or order is canceled.']);
            }
            $shift = auth()->check() ? Shift::where('user_id', auth()->id())->where('branch_id', $order->branch_id)->whereNull('closed_at')->first() : null;
            Payment::create(['shift_id' => $method === 'cash' ? $shift?->id : null, 'order_id' => $order->id, 'amount' => $amount, 'method' => $method, 'reference' => $reference]);
            $this->ledger->pair($order->branch_id, 'payment:'.$reference, 'Order payment '.$order->number, $method === 'cod' ? 'COD clearing' : 'Cash & bank', 'Accounts receivable', $amount);
            $order->update(['payment_status' => $order->paid >= $order->total - $refundedValue ? 'paid' : 'partial']);
            Audit::record('payment.recorded', $order->number, ['amount' => $amount, 'method' => $method]);
        });
    }

    public function transition(Order $order, string $status): void
    {
        DB::transaction(function () use ($order, $status) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $next = ['pending' => ['confirmed', 'canceled'], 'confirmed' => ['processing', 'canceled'], 'processing' => ['packed'], 'packed' => ['shipped'], 'shipped' => ['delivered', 'failed_delivery'], 'failed_delivery' => ['shipped'], 'delivered' => ['completed'], 'completed' => [], 'canceled' => []];
            if (! in_array($status, $next[$order->status] ?? [])) {
                throw ValidationException::withMessages(['status' => 'This status transition is not allowed.']);
            }
            if ($status === 'completed' && $order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['status' => 'Collect payment before completing the order.']);
            }
            if ($status === 'canceled') {
                if ($order->paid > 0) {
                    throw ValidationException::withMessages(['status' => 'Paid orders must use the return/refund workflow.']);
                }
                foreach ($order->items as $item) {
                    $this->inventory->move($order->branch_id, $item->variant_id, $item->quantity, 'cancel', $order->number);
                }
                $cost = (int) $order->items->sum(fn ($i) => $i->quantity * $i->unit_cost);
                $this->ledger->post($order->branch_id, 'cancel:'.$order->id, 'Canceled '.$order->number, [
                    ['account' => 'Sales revenue', 'debit' => $order->subtotal, 'credit' => 0], ['account' => 'Delivery revenue', 'debit' => $order->shipping, 'credit' => 0],
                    ['account' => 'Accounts receivable', 'debit' => 0, 'credit' => $order->total], ['account' => 'Inventory', 'debit' => $cost, 'credit' => 0], ['account' => 'Cost of goods sold', 'debit' => 0, 'credit' => $cost],
                ]);
            }
            $values = ['status' => $status];
            if (in_array($status, ['packed', 'shipped', 'delivered'])) {
                $values['fulfillment_status'] = $status;
            }
            $order->update($values);
            Audit::record('order.'.$status, $order->number);
        });
    }

    public function returnItem(Order $order, int $itemId, int $quantity, string $disposition, string $reference): void
    {
        DB::transaction(function () use ($order, $itemId, $quantity, $disposition, $reference) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if (JournalEntry::where('reference', 'return:'.$reference)->exists()) {
                return;
            }
            $item = $order->items()->findOrFail($itemId);
            if (! in_array($order->status, ['completed', 'delivered']) || $quantity < 1 || $quantity > $item->quantity - $item->returned) {
                throw ValidationException::withMessages(['quantity' => 'Only received items from delivered orders can be returned, within original quantities.']);
            }
            if (! in_array($disposition, ['sellable', 'damaged'])) {
                throw ValidationException::withMessages(['disposition' => 'Choose a valid stock disposition.']);
            }
            $value = $quantity * $item->unit_price;
            $cost = $quantity * $item->unit_cost;
            $previousReturns = (int) $order->items->sum(fn ($i) => $i->returned * $i->unit_price);
            $due = max(0, $order->total - $previousReturns - $order->paid);
            $offset = min($value, $due);
            $refund = $value - $offset;
            $lines = [['account' => 'Sales returns', 'debit' => $value, 'credit' => 0], ['account' => 'Accounts receivable', 'debit' => 0, 'credit' => $offset], ['account' => 'Cash & bank', 'debit' => 0, 'credit' => $refund], ['account' => $disposition === 'sellable' ? 'Inventory' : 'Damage expense', 'debit' => $cost, 'credit' => 0], ['account' => 'Cost of goods sold', 'debit' => 0, 'credit' => $cost]];
            $this->ledger->post($order->branch_id, 'return:'.$reference, 'Return '.$order->number, $lines);
            $shift = auth()->check() ? Shift::where('user_id', auth()->id())->where('branch_id', $order->branch_id)->whereNull('closed_at')->first() : null;
            if ($refund > 0) {
                Payment::create(['shift_id' => $shift?->id, 'order_id' => $order->id, 'amount' => -$refund, 'method' => 'refund', 'reference' => 'refund:'.$reference, 'note' => $disposition]);
            }
            if ($disposition === 'sellable') {
                $this->inventory->move($order->branch_id, $item->variant_id, $quantity, 'return', $reference);
            }
            $item->increment('returned', $quantity);
            $order->update(['payment_status' => $order->paid >= $order->total - $previousReturns - $value ? 'paid' : 'partial']);
            Audit::record('order.returned',$order->number,['item' => $itemId, 'quantity' => $quantity, 'refund' => $refund, 'disposition' => $disposition]);
        });
    }
}
