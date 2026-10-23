<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Local gateway contract demonstration. It never contacts a financial provider. */
class DemoGateway
{
    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('app.key'));
    }

    public function initiate(Order $order): PaymentAttempt
    {
        abort_unless(config('business.demo'), 403, 'The simulated gateway is available only in demo mode.');
        app(Modules::class)->require('orders');

        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $returns = (int) $order->items->sum(fn ($i) => $i->returned * $i->unit_price);
            $amount = $order->total - $returns - $order->paid;
            if ($amount < 1 || $order->status === 'canceled') {
                throw ValidationException::withMessages(['payment' => 'There is no payable balance.']);
            }

            return PaymentAttempt::create(['order_id' => $order->id, 'reference' => (string) Str::uuid(), 'amount' => $amount]);
        });
    }

    public function callback(string $body, string $signature): void
    {
        abort_unless(config('business.demo') && hash_equals($this->sign($body), $signature), 403, 'Invalid demo gateway signature.');
        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        $data = validator($data, ['reference' => 'required|uuid', 'amount' => 'required|integer|min:1', 'status' => 'required|in:success,failed'])->validate();
        $attempt = PaymentAttempt::withoutGlobalScopes()->where('reference', $data['reference'])->firstOrFail();
        $context = app(TenantContext::class);
        $previous = $context->id();
        $context->set($attempt->organization_id);
        try {
            DB::transaction(function () use ($attempt, $data) {
                $attempt = PaymentAttempt::lockForUpdate()->findOrFail($attempt->id);
                if ($attempt->provider !== 'demo' || $attempt->amount !== (int) $data['amount']) {
                    throw ValidationException::withMessages(['amount' => 'Gateway amount mismatch.']);
                }
                if ($attempt->status !== 'pending') {
                    return;
                }
                if ($data['status'] === 'success') {
                    app(Orders::class)->pay(Order::findOrFail($attempt->order_id), $attempt->amount, 'demo_online', 'demo-gateway:'.$attempt->reference);
                }
                $attempt->update(['status' => $data['status']]);
                Audit::record('payment.demo-callback', $attempt->reference, ['status' => $data['status']]);
            });
        } finally {
            $context->set($previous);
        }
    }

    public function simulate(Order $order, string $outcome): void
    {
        $attempt = $this->initiate($order);
        $body = json_encode(['reference' => $attempt->reference, 'amount' => $attempt->amount, 'status' => $outcome], JSON_THROW_ON_ERROR);
        $this->callback($body,$this->sign($body));
    }
}
