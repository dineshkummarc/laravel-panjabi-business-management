<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DemoGateway;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function simulate(Request $r, string $shop, string $token, DemoGateway $gateway)
    {
        $d = $r->validate(['outcome' => 'required|in:success,failed']);
        $gateway->simulate(Order::where('token', $token)->firstOrFail(), $d['outcome']);

        return back()->with('success', $d['outcome'] === 'success' ? 'Simulated online payment verified. No money was charged.' : 'Simulated gateway declined the payment. No order payment was recorded.');
    }

    public function callback(Request $r, DemoGateway $gateway)
    {
        $gateway->callback($r->getContent(), $r->header('X-Demo-Signature', ''));

        return response()->json(['received' => true]);
    }
}
