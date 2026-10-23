<?php

namespace App\Http\Middleware;

use Closure;

class BusinessAccess
{
    public function handle($request, Closure $next)
    {
        abort_unless(auth()->check() && auth()->user()->role !== 'customer' && auth()->user()->organization_id, 403);
        abort_unless(auth()->user()->organization->active, 403, 'This business is currently suspended.');

        return $next($request);
    }
}
