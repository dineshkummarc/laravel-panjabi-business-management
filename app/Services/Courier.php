<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Courier
{
    public function __construct(private Orders $orders, private Ledger $ledger) {}

    public function book(Order $order): Shipment
    {
        app(Modules::class)->require('courier');

        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($shipment = Shipment::where('order_id', $order->id)->first()) {
                return $shipment;
            }
            if ($order->status !== 'packed') {
                throw ValidationException::withMessages(['courier' => 'Pack the order before booking a simulated shipment.']);
            }
            $shipment = Shipment::create(['order_id' => $order->id, 'provider' => 'demo', 'reference' => 'SIM-'.strtoupper(Str::random(12)), 'cod_amount' => max(0, $order->total - $order->paid)]);
            $this->orders->transition($order, 'shipped');
            Audit::record('courier.simulated-booking', $shipment->reference);

            return $shipment;
        });
    }

    public function deliver(Shipment $shipment): void
    {
        DB::transaction(function () use ($shipment) {
            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            if ($shipment->status === 'delivered' || $shipment->status === 'remitted') {
                return;
            }
            $order = Order::findOrFail($shipment->order_id);
            $this->orders->transition($order, 'delivered');
            if ($shipment->cod_amount) {
                $this->orders->pay($order, $shipment->cod_amount, 'cod', 'sim-cod:'.$shipment->id);
            }
            $shipment->update(['status' => 'delivered']);
            Audit::record('courier.simulated-delivery', $shipment->reference);
        });
    }

    public function remit(Shipment $shipment, int $fee): void
    {
        DB::transaction(function () use ($shipment, $fee) {
            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            if ($shipment->status === 'remitted') {
                return;
            }
            if ($shipment->status !== 'delivered' || $fee < 0 || $fee > $shipment->cod_amount) {
                throw ValidationException::withMessages(['fee' => 'Deliver first; fee must not exceed collected COD.']);
            }
            $order = Order::findOrFail($shipment->order_id);
            $this->ledger->post($order->branch_id, 'courier-remit:'.$shipment->id, 'Simulated courier remittance', [
                ['account' => 'Cash & bank', 'debit' => $shipment->cod_amount - $fee, 'credit' => 0], ['account' => 'Courier expense', 'debit' => $fee, 'credit' => 0], ['account' => 'COD clearing', 'debit' => 0, 'credit' => $shipment->cod_amount],
            ]);
            $shipment->update(['fee' => $fee, 'remitted' => $shipment->cod_amount - $fee, 'status' => 'remitted']);
            Audit::record('courier.simulated-remittance', $shipment->reference, ['fee' => $fee]);
        });
    }
}
