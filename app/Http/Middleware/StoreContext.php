<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\TenantContext;
use Closure;

class StoreContext
{
    public function handle($request, Closure $next)
    {
        $organization = Organization::where('slug', $request->route('shop'))->firstOrFail();
        if (auth()->check() && auth()->user()->organization_id !== $organization->id) {
            abort(403, 'Sign out before visiting another business.');
        }
        app(TenantContext::class)->set($organization->id);
        abort_unless($organization->active && $organization->enabled('storefront'), 404);
        view()->share('store', $organization);
        try {
            return $next($request);
        } finally {
            app(TenantContext::class)->set(null);
            view()->share('store', null);
        }
    }
}
