<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use App\Services\Audit;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('business.settings', ['organization' => auth()->user()->organization]);
    }

    public function save(Request $r)
    {
        abort_if(config('business.demo'), 403, 'Business configuration changes are disabled in the shared demo.');
        $d = $r->validate(['name' => 'required|string|max:120', 'phone' => 'nullable|string|max:30', 'address' => 'nullable|string|max:1000', 'delivery_fee' => 'required|integer|min:0|max:1000000', 'delivery_policy' => 'nullable|string|max:5000', 'return_policy' => 'nullable|string|max:5000']);
        auth()->user()->organization->update($d);
        Audit::record('settings.updated', 'organization');

        return back()->with('success', 'Business settings updated.');
    }

    public function requests()
    {
        return view('business.requests', ['requests' => CustomerRequest::with('order')->latest()->paginate(20)]);
    }

    public function resolve(Request $r, CustomerRequest $customerRequest)
    {
        $d = $r->validate(['status' => 'required|in:acknowledged,resolved,rejected']);
        $customerRequest->update($d);
        Audit::record('customer-request.'.$d['status'], (string) $customerRequest->id);

        return back()->with('success', 'Request updated. Process any refund or cancellation from the order page.');
    }
}
