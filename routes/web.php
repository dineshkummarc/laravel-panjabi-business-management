<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StoreController;
use App\Http\Middleware\BusinessAccess;
use App\Http\Middleware\OwnerOnly;
use App\Http\Middleware\StoreContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');
Route::view('/features', 'marketing.features');
Route::view('/demo', 'marketing.demo');
Route::view('/contact', 'marketing.contact');
Route::get('/policies/{page}', function (string $page) {
    abort_unless(in_array($page, ['privacy', 'terms', 'delivery', 'returns', 'size-guide']), 404);

    return view('marketing.policy', compact('page'));
});
Route::view('/login', 'auth.login')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::view('/register', 'auth.register')->middleware('guest');
Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::prefix('/shop/{shop}')->middleware(StoreContext::class)->group(function () {
    Route::get('/', [StoreController::class, 'index']);
    Route::get('/products/{slug}', [StoreController::class, 'product']);
    Route::get('/cart', [StoreController::class, 'cart']);
    Route::post('/cart', [StoreController::class, 'add']);
    Route::post('/cart/remove', [StoreController::class, 'remove']);
    Route::post('/checkout', [StoreController::class, 'checkout'])->middleware('throttle:15,1');
    Route::get('/track/{token}', [StoreController::class, 'track'])->middleware('throttle:30,1');
    Route::post('/track/{token}/demo-payment', [GatewayController::class, 'simulate'])->middleware('throttle:10,1');
    Route::get('/branches', [StoreController::class, 'branches']);
    Route::get('/campaigns/{slug}', [StoreController::class, 'campaign']);
    Route::get('/signup', [StoreController::class, 'signupForm'])->middleware('guest');
    Route::post('/signup', [StoreController::class, 'signup'])->middleware(['guest', 'throttle:5,1']);
    Route::post('/orders/{order}/request', [StoreController::class, 'requestChange'])->middleware('auth');
    Route::get('/wishlist', [StoreController::class, 'wishlist']);
    Route::post('/wishlist', [StoreController::class, 'wish']);
    Route::get('/policies/{page}', [StoreController::class, 'policy']);
    Route::get('/account', [StoreController::class, 'account'])->middleware('auth');
});
Route::prefix('/business')->middleware(['auth', BusinessAccess::class])->controller(BusinessController::class)->group(function () {
    Route::get('/', 'dashboard');
    Route::get('/orders', 'orders');
    Route::get('/orders/{order}', 'order');
    Route::post('/orders/{order}/status', 'status');
    Route::post('/orders/{order}/payment', 'payment');
    Route::post('/orders/{order}/return', 'returns');
    Route::get('/orders/{order}/invoice', 'invoice');
    Route::get('/orders/{order}/pdf', 'pdf');
    Route::get('/pos', 'pos');
    Route::post('/pos', 'sell');
    Route::get('/inventory', 'inventory');
    Route::post('/inventory/adjust', 'adjust');
    Route::post('/inventory/transfer', 'transfer');
    Route::post('/transfers/{transfer}/receive', 'transferReceive');
    Route::get('/purchases', 'purchases');
    Route::post('/purchases', 'purchaseCreate');
    Route::post('/purchases/{purchase}/receive', 'purchaseReceive');
    Route::post('/purchases/{purchase}/pay', 'purchasePay');
    Route::get('/people', 'people');
    Route::post('/attendance', 'attendance');
    Route::post('/attendance/simulate', 'attendanceDemo');
    Route::post('/payroll', 'payroll');
    Route::get('/tailoring', 'tailoring');
    Route::post('/tailoring', 'tailoringCreate');
    Route::post('/tailoring/{job}', 'tailoringUpdate');
    Route::get('/production', 'production');
    Route::post('/production', 'productionCreate');
    Route::post('/production/{batch}/complete', 'produce');
    Route::get('/reports', 'reports');
    Route::post('/expenses', 'expense');
    Route::get('/modules', 'modules');
    Route::post('/modules', 'modulesUpdate');
    Route::get('/shifts', 'shifts');
    Route::post('/shifts', 'shiftOpen');
    Route::post('/shifts/{shift}/close', 'shiftClose');
});

Route::prefix('/business')->middleware(['auth', BusinessAccess::class])->controller(CatalogController::class)->group(function () {
    Route::get('/catalog/import', 'imports');
    Route::post('/catalog/preview', 'preview');
    Route::post('/catalog/commit', 'commit');
    Route::get('/catalog/export', 'export');
    Route::get('/staff', 'staff');
    Route::post('/staff', 'staffCreate');
});

Route::prefix('/business')->middleware(['auth', BusinessAccess::class])->controller(AiController::class)->group(function () {
    Route::get('/ai', 'index');
    Route::post('/ai', 'generate')->middleware('throttle:10,1');
    Route::post('/ai/{draft}/approve', 'approve');
});

Route::middleware(['auth', BusinessAccess::class, OwnerOnly::class])->controller(SettingsController::class)->group(function () {
    Route::get('/business/settings', 'index');
    Route::post('/business/settings', 'save');
    Route::get('/business/requests', 'requests');
    Route::post('/business/requests/{customerRequest}', 'resolve');
});
Route::middleware('auth')->controller(PlatformController::class)->group(function () {
    Route::get('/platform', 'index');
    Route::post('/platform/{organization}/toggle', 'toggle');
});

Route::prefix('/business/courier')->middleware(['auth', BusinessAccess::class])->controller(CourierController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'book');
    Route::post('/{shipment}/deliver', 'deliver');
    Route::post('/{shipment}/remit', 'remit');
});

Route::post('/integrations/demo/payment-callback', [GatewayController::class, 'callback'])->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:30,1');

Route::post('/language/{locale}',function (Request $request,string $locale) {
    abort_unless(in_array($locale,['en', 'bn']),404);
    session(['locale' => $locale]);

    return back();
});
