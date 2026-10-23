<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Services\Modules;
use App\Services\Orders;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function index(Request $request, string $shop)
    {
        $query = Product::where('active', true)->where('kind', 'finished')->with(['variants', 'category']);
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.mb_substr($request->string('q'), 0, 100).'%');
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        return view('store.index', ['products' => $query->latest()->paginate(12)->withQueryString(), 'categories' => Category::all()]);
    }

    public function product(string $shop, string $slug)
    {
        $product = Product::where('slug', $slug)->where('active', true)->where('kind', 'finished')->with('variants.stocks')->firstOrFail();

        return view('store.product', compact('product'));
    }

    public function add(Request $request, string $shop)
    {
        $data = $request->validate(['variant_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:100']);
        $variant = Variant::with('product')->findOrFail($data['variant_id']);
        abort_unless($variant->product->active && $variant->product->kind === 'finished', 404);
        $cart = session('cart.'.$shop, []);
        $cart[$variant->id] = min(100, ($cart[$variant->id] ?? 0) + $data['quantity']);
        session(['cart.'.$shop => $cart]);

        return redirect('/shop/'.$shop.'/cart')->with('success', 'Added to your bag.');
    }

    public function cart(string $shop)
    {
        $cart = session('cart.'.$shop, []);
        $variants = Variant::whereIn('id', array_keys($cart))->with('product')->get();

        return view('store.cart', ['cart' => $cart, 'variants' => $variants, 'branches' => Branch::where('active', true)->get(), 'checkoutKey' => (string) Str::uuid()]);
    }

    public function remove(Request $request, string $shop)
    {
        $cart = session('cart.'.$shop, []);
        unset($cart[$request->integer('variant_id')]);
        session(['cart.'.$shop => $cart]);

        return back();
    }

    public function checkout(Request $request, string $shop, Orders $orders)
    {
        app(Modules::class)->require('orders');
        $data = $request->validate(['customer_name' => 'required|string|max:120', 'phone' => 'required|regex:/^(?:\+?88)?01[3-9][0-9]{8}$/', 'address' => 'required|string|max:1000', 'branch_id' => 'required|integer', 'idempotency_key' => 'required|uuid']);
        $data['channel'] = 'web';
        $data['payment_method'] = 'cod';
        $order = $orders->place($data, session('cart.'.$shop, []), $data['idempotency_key']);
        session()->forget('cart.'.$shop);

        return redirect('/shop/'.$shop.'/track/'.$order->token)->with('success', 'Order received. Keep this private tracking link.');
    }

    public function track(string $shop, string $token)
    {
        return view('store.track', ['order' => Order::with('items')->where('token', $token)->firstOrFail()]);
    }

    public function account()
    {
        abort_unless(auth()->user()->role === 'customer', 403);

        return view('store.account', ['orders' => Order::where('user_id', auth()->id())->latest()->paginate(15)]);
    }

    public function branches()
    {
        return view('store.branches', ['branches' => Branch::where('active', true)->get()]);
    }

    public function campaign(string $shop, string $slug)
    {
        $campaign = Campaign::where('slug', $slug)->where('active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->firstOrFail();
        $products = Product::where('active', true)->where('kind', 'finished')->when($campaign->category_id, fn ($q) => $q->where('category_id', $campaign->category_id))->with('variants')->paginate(12);

        return view('store.campaign', compact('campaign', 'products'));
    }

    public function signupForm()
    {
        return view('store.signup');
    }

    public function signup(Request $r, string $shop)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users', 'password' => 'required|string|min:12|confirmed']);
        $user = User::create($d + ['role' => 'customer', 'organization_id' => app(TenantContext::class)->id()]);
        auth()->login($user);
        $r->session()->regenerate();

        return redirect('/shop/'.$shop.'/account');
    }

    public function requestChange(Request $r, string $shop, Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $d = $r->validate(['type' => 'required|in:cancel,return', 'reason' => 'required|string|min:10|max:2000']);
        CustomerRequest::create($d + ['order_id' => $order->id, 'user_id' => auth()->id()]);

        return back()->with('success', 'Your request was sent to the store. Staff will review it.');
    }

    public function wish(Request $r, string $shop)
    {
        $d = $r->validate(['product_id' => 'required|integer']);
        Product::where('active', true)->findOrFail($d['product_id']);
        $ids = session('wishlist.'.$shop, []);
        $ids = in_array($d['product_id'], $ids) ? array_values(array_diff($ids, [$d['product_id']])) : array_slice([...$ids, $d['product_id']], -100);
        session(['wishlist.'.$shop => $ids]);

        return back()->with('success', 'Wishlist updated.');
    }

    public function wishlist(string $shop)
    {
        return view('store.wishlist', ['products' => Product::where('active', true)->whereIn('id', session('wishlist.'.$shop, []))->with('variants')->get()]);
    }

    public function policy(string $shop, string $page)
    {
        abort_unless(in_array($page,['delivery', 'returns']),404);
        $store = Organization::findOrFail(app(TenantContext::class)->id());

        return view('store.policy',compact('store','page'));
    }
}
