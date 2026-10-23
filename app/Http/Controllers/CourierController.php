<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Courier;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    private function access(): void
    {
        abort_unless(auth()->user()->allows('orders'), 403);
    }

    public function index()
    {
        $this->access();

        return view('business.courier', ['shipments' => Shipment::whereHas('order')->with('order')->latest()->paginate(20), 'orders' => Order::where('status', 'packed')->get()]);
    }

    public function book(Request $r, Courier $courier)
    {
        $this->access();
        $r->validate(['order_id' => 'required|integer']);
        $courier->book(Order::findOrFail($r->integer('order_id')));

        return back()->with('success', 'Simulated shipment booked. No external courier was contacted.');
    }

    public function deliver(Shipment $shipment, Courier $courier)
    {
        $this->access();
        Order::findOrFail($shipment->order_id);
        $courier->deliver($shipment);

        return back()->with('success', 'Simulated delivery and COD collection recorded.');
    }

    public function remit(Request $r, Shipment $shipment, Courier $courier)
    {
        $this->access();
        Order::findOrFail($shipment->order_id);
        $d = $r->validate(['fee' => 'required|integer|min:0']);
        $courier->remit($shipment, $d['fee']);

        return back()->with('success', 'Simulated remittance reconciled.');
    }
}
