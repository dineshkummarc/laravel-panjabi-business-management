<?php

namespace App\Http\Middleware;

use Closure;

class OwnerOnly
{
    public function handle($request, Closure $next)
    {
        abort_unless(auth()->check() && in_array(auth()->user()->role, ['owner', 'admin']), 403);

        return $next($request);
    }
}
