<?php

namespace App\Http\Middleware;

use Closure;

class Locale
{
    public function handle($request, Closure $next)
    {
        app()->setLocale(in_array(session('locale'), ['en', 'bn']) ? session('locale') : 'en');

        return $next($request);
    }
}
