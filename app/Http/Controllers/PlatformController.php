<?php

namespace App\Http\Controllers;

use App\Models\Organization;

class PlatformController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->role === 'platform', 403);

        return view('platform.index', ['organizations' => Organization::withCount(['branches' => fn ($q) => $q->withoutGlobalScopes()])->paginate(20)]);
    }

    public function toggle(Organization $organization)
    {
        abort_unless(auth()->user()->role === 'platform', 403);
        abort_if(config('business.demo'), 403, 'Suspension is disabled in the shared demo.');
        $organization->update(['active' => ! $organization->active]);

        return back()->with('success', 'Business status updated.');
    }
}
