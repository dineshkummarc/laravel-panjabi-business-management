<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.$request->ip().':'.strtolower($credentials['email']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in a minute.']);
        }
        RateLimiter::hit($key, 60);
        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        if (auth()->user()->role === 'platform') {
            return redirect('/platform');
        }

        return redirect(auth()->user()->role === 'customer' ? '/shop/'.auth()->user()->organization->slug.'/account' : '/business');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'business' => 'required|string|max:120', 'email' => 'required|email|unique:users', 'password' => 'required|min:12|confirmed', 'type' => 'required|in:retail,online,wholesale,manufacturer,tailoring']);
        $user = DB::transaction(function () use ($data) {
            $modules = match ($data['type']) {
                'online' => ['inventory', 'orders', 'storefront', 'accounting', 'crm'],
                'tailoring' => ['tailoring', 'accounting', 'crm'],
                'wholesale' => ['inventory', 'orders', 'wholesale', 'purchasing', 'accounting', 'crm'],
                'manufacturer' => array_keys(config('business.modules')),
                default => ['inventory', 'orders', 'pos', 'purchasing', 'accounting', 'crm'],
            };
            $organization = Organization::create(['name' => $data['business'], 'slug' => Str::slug($data['business']).'-'.Str::lower(Str::random(5)), 'type' => $data['type'], 'modules' => $modules]);
            app(TenantContext::class)->set($organization->id);
            Branch::create(['name' => 'Main branch', 'code' => 'MAIN']);

            return User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'organization_id' => $organization->id, 'role' => 'owner']);
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/business')->with('success', 'Your business is ready. Add your products and opening stock to begin.');
    }
}
