<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\Purchase;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\TailoringJob;
use App\Models\User;
use App\Models\Variant;
use App\Services\AiCatalog;
use App\Services\AttendanceImport;
use App\Services\CatalogCsv;
use App\Services\Courier;
use App\Services\DemoGateway;
use App\Services\Inventory;
use App\Services\Ledger;
use App\Services\Modules;
use App\Services\Operations;
use App\Services\Orders;
use App\Services\SafePageReader;
use App\Services\TenantContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BusinessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Branch $branch;

    private Branch $otherBranch;

    private Variant $variant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['business.demo' => true]);
        $this->org = Organization::create(['name' => 'Test Shop', 'slug' => 'test-shop', 'modules' => array_keys(config('business.modules'))]);
        app(TenantContext::class)->set($this->org->id);
        $this->branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK']);
        $this->otherBranch = Branch::create(['name' => 'Sylhet', 'code' => 'SYL']);
        $p = Product::create(['name' => 'Cotton Panjabi', 'slug' => 'cotton-panjabi', 'active' => true, 'image' => '/images/panjabi-sage.svg']);
        $this->variant = Variant::create(['product_id' => $p->id, 'sku' => 'TEST-40', 'size' => '40', 'color' => 'Sage', 'price' => 200000, 'wholesale_price' => 175000, 'cost' => 100000]);
        app(Inventory::class)->move($this->branch->id, $this->variant->id, 10, 'opening', 'TEST');
        app(Ledger::class)->pair($this->branch->id, 'opening-test', 'Opening', 'Inventory', 'Opening equity', 1000000);
        $this->owner = User::create(['organization_id' => $this->org->id, 'name' => 'Owner', 'email' => 'owner@test.example', 'password' => 'password', 'role' => 'owner']);
    }

    private function place(array $cart = [], string $key = ''): Order
    {
        return app(Orders::class)->place(['branch_id' => $this->branch->id, 'customer_name' => 'Rahim', 'phone' => '01712345678', 'channel' => 'web', 'address' => 'Dhaka'], $cart ?: [$this->variant->id => 2], $key ?: Str::uuid());
    }

    private function balanced(): void
    {
        $this->assertEquals(JournalLine::sum('debit'), JournalLine::sum('credit'));
    }

    public function test_order_payment_fulfillment_and_partial_return_reconcile(): void
    {
        $o = $this->place();
        $this->assertEquals(412000, $o->total);
        $this->assertEquals(8, StockLevel::first()->quantity);
        $service = app(Orders::class);
        $service->pay($o, 212000, 'cash', 'cash-1');
        $service->pay($o, 200000, 'bank', 'bank-1');
        foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered', 'completed'] as $s) {
            $service->transition($o, $s);
        }
        $service->returnItem($o, $o->items()->first()->id, 1, 'sellable', 'return-1');
        $this->assertEquals(9, StockLevel::first()->quantity);
        $this->assertEquals(212000, $o->fresh()->paid);
        $this->assertEquals(1, $o->items()->first()->returned);
        $this->balanced();
    }

    public function test_checkout_payment_and_return_are_idempotent(): void
    {
        $o = $this->place(key: 'unique-checkout');
        $again = $this->place(key: 'unique-checkout');
        $this->assertSame($o->id, $again->id);
        $this->assertEquals(8, StockLevel::first()->quantity);
        app(Orders::class)->pay($o, 100000, 'cash', 'same-payment');
        app(Orders::class)->pay($o, 100000, 'cash', 'same-payment');
        $this->assertEquals(1, Payment::count());
        $this->balanced();
    }

    public function test_oversell_rolls_back_every_business_effect(): void
    {
        try {
            $this->place([$this->variant->id => 11]);
            $this->fail('Must reject oversell.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('stock', implode(' ', array_keys($e->errors())));
        }
        $this->assertEquals(0, Order::count());
        $this->assertEquals(10, StockLevel::first()->quantity);
        $this->assertEquals(1, JournalEntry::count());
    }

    public function test_cancel_reverses_stock_and_financial_postings(): void
    {
        $o = $this->place();
        app(Orders::class)->transition($o, 'canceled');
        $this->assertEquals(10, StockLevel::first()->quantity);
        $this->assertEquals(0, JournalLine::where('account', 'Sales revenue')->sum('credit') - JournalLine::where('account', 'Sales revenue')->sum('debit'));
        $this->balanced();
    }

    public function test_overpayment_and_illegal_transition_are_rejected(): void
    {
        $o = $this->place();
        $this->expectException(ValidationException::class);
        app(Orders::class)->pay($o, 9999999, 'cash', 'bad');
    }

    public function test_purchase_partial_receipt_and_supplier_payment(): void
    {
        $supplier = Supplier::create(['name' => 'Textiles', 'phone' => '01800000000']);
        $p = Purchase::create(['branch_id' => $this->branch->id, 'supplier_id' => $supplier->id, 'variant_id' => $this->variant->id, 'quantity' => 10, 'unit_cost' => 100000, 'reference' => 'PO-TEST']);
        app(Operations::class)->receive($p, 4);
        app(Operations::class)->paySupplier($p, 200000);
        $this->assertEquals(14, StockLevel::first()->quantity);
        $this->assertEquals('partial', $p->fresh()->status);
        $this->assertEquals(200000, $p->fresh()->paid);
        $this->balanced();
    }

    public function test_partial_branch_transfer_conserves_units_including_transit(): void
    {
        $t = app(Operations::class)->dispatch($this->branch->id, $this->otherBranch->id, $this->variant->id, 5);
        app(Operations::class)->receiveTransfer($t, 2);
        $this->assertEquals(7, StockLevel::sum('quantity'));
        $this->assertEquals(3, $t->fresh()->quantity - $t->fresh()->received);
        $this->balanced();
    }

    public function test_module_dependencies_and_direct_endpoint_enforcement(): void
    {
        try {
            app(Modules::class)->update($this->org, ['pos']);
            $this->fail('Dependency validation expected.');
        } catch (ValidationException) {
        }
        app(Modules::class)->update($this->org, ['inventory', 'orders', 'accounting']);
        $this->actingAs($this->owner)->get('/business/pos')->assertForbidden();
    }

    public function test_tenant_and_branch_guards_block_tampered_ids(): void
    {
        $o = $this->place();
        $second = Organization::create(['name' => 'Other', 'slug' => 'other', 'modules' => ['inventory']]);
        $foreign = User::create(['organization_id' => $second->id, 'name' => 'Other', 'email' => 'other@test.example', 'password' => 'password', 'role' => 'owner']);
        app(TenantContext::class)->set(null);
        $this->actingAs($foreign)->get('/business/orders/'.$o->id)->assertNotFound();
        $cashier = User::create(['organization_id' => $this->org->id, 'branch_id' => $this->otherBranch->id, 'name' => 'Cashier', 'email' => 'cashier@test.example', 'password' => 'password', 'role' => 'cashier']);
        $this->actingAs($cashier)->get('/business/orders/'.$o->id)->assertNotFound();
        $this->actingAs($cashier)->get('/business/modules')->assertForbidden();
        $this->actingAs($cashier)->get('/business/reports')->assertForbidden();
    }

    public function test_no_context_returns_no_business_data(): void
    {
        app(TenantContext::class)->set(null);
        $this->assertEquals(0, Product::count());
    }

    public function test_cross_tenant_foreign_keys_are_rejected(): void
    {
        $other = Organization::create(['name' => 'Other', 'slug' => 'other', 'modules' => []]);
        app(TenantContext::class)->set($other->id);
        $p = Product::create(['name' => 'Other product', 'slug' => 'other-product']);
        app(TenantContext::class)->set($this->org->id);
        $this->expectException(ValidationException::class);
        Variant::create(['product_id' => $p->id, 'sku' => 'ATTACK', 'size' => '40', 'color' => 'X', 'price' => 1, 'cost' => 1]);
    }

    public function test_tailoring_handover_requires_full_payment_and_recognizes_revenue_once(): void
    {
        $job = TailoringJob::create(['branch_id' => $this->branch->id, 'customer_name' => 'Rahim', 'phone' => '01712345678', 'design' => 'Custom', 'measurements' => ['chest' => 42], 'due_date' => now()->addWeek(), 'price' => 300000, 'deposit' => 100000]);
        $s = app(Operations::class);
        $s->tailoringPayment($job, 100000);
        foreach (['cutting', 'sewing', 'fitting', 'ready'] as $stage) {
            $s->tailoringStatus($job, $stage);
        }
        try {
            $s->tailoringStatus($job, 'delivered');
            $this->fail('Balance must be paid.');
        } catch (ValidationException) {
        }
        $s->tailoringPayment($job, 200000);
        $s->tailoringStatus($job, 'delivered');
        $this->assertEquals('delivered', $job->fresh()->status);
        $this->balanced();
    }

    public function test_production_and_payroll_do_not_post_twice(): void
    {
        $p = Product::create(['name' => 'Fabric', 'slug' => 'fabric', 'kind' => 'material']);
        $v = Variant::create(['product_id' => $p->id, 'sku' => 'RAW', 'size' => 'Meter', 'color' => 'White', 'price' => 0, 'cost' => 20000]);
        app(Inventory::class)->move($this->branch->id, $v->id, 50, 'opening', 'RAW');
        $batch = ProductionBatch::create(['branch_id' => $this->branch->id, 'name' => 'Test', 'variant_id' => $this->variant->id, 'material_id' => $v->id, 'quantity' => 5, 'material_quantity' => 15, 'labor_cost' => 100000]);
        $s = app(Operations::class);
        $s->produce($batch);
        $s->produce($batch);
        $this->assertEquals(35, StockLevel::where('variant_id', $v->id)->value('quantity'));
        $this->assertEquals(15, StockLevel::where('variant_id', $this->variant->id)->value('quantity'));
        $e = Employee::create(['branch_id' => $this->branch->id, 'name' => 'Staff', 'phone' => '01700000000', 'position' => 'Tailor', 'salary' => 2000000, 'joined_at' => '2026-01-01']);
        $s->payroll($e, '2026-10', 100000);
        $s->payroll($e, '2026-10', 100000);
        $this->assertEquals(1, Payroll::count());
        $this->balanced();
    }

    public function test_guest_checkout_through_http_and_secure_tracking(): void
    {
        app(TenantContext::class)->set(null);
        $this->post('/shop/test-shop/cart', ['variant_id' => $this->variant->id, 'quantity' => 1])->assertRedirect('/shop/test-shop/cart');
        $response = $this->post('/shop/test-shop/checkout', ['customer_name' => 'Demo Buyer', 'phone' => '01712345678', 'address' => 'Dhanmondi, Dhaka', 'branch_id' => $this->branch->id, 'idempotency_key' => (string) Str::uuid()]);
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->get($url)->assertOk()->assertSee('Demo Buyer');
        $this->get('/shop/test-shop/track/1')->assertNotFound();
    }

    public function test_public_and_business_pages_render_with_real_demo_data(): void
    {
        $this->seed(DemoSeeder::class);
        app(TenantContext::class)->set(null);
        foreach (['/', '/features', '/demo', '/contact', '/policies/privacy', '/policies/size-guide', '/login', '/register', '/shop/aarong-demo', '/shop/aarong-demo/branches', '/shop/aarong-demo/cart'] as $url) {
            $this->get($url)->assertOk();
        }
        $owner = User::where('email', 'owner@panjabi.demo')->first();
        $this->actingAs($owner);
        foreach (['/business', '/business/orders', '/business/pos', '/business/inventory', '/business/purchases', '/business/people', '/business/tailoring', '/business/production', '/business/reports', '/business/modules', '/business/shifts', '/admin', '/admin/products', '/admin/products/create', '/admin/variants', '/admin/categories', '/admin/branches', '/admin/customers', '/admin/suppliers', '/admin/employees', '/tools/admin-panel'] as $url) {
            $this->get($url)->assertOk();
        }
        $o = Order::first();
        $this->get('/business/orders/'.$o->id)->assertOk();
        $this->get('/business/orders/'.$o->id.'/invoice')->assertOk();
    }

    public function test_ai_draft_requires_review_and_does_not_create_product_automatically(): void
    {
        $before = Product::count();
        $draft = app(AiCatalog::class)->draft('Cotton Eid Panjabi', 'keyword', 'en');
        $this->assertEquals($before, Product::count());
        $this->assertEquals('draft', $draft->status);
        $product = app(AiCatalog::class)->approve($draft, 'Reviewed Cotton Panjabi', 'Verified description.');
        $this->assertFalse($product->active);
        $this->assertEquals('approved', $draft->fresh()->status);
    }

    public function test_unicode_invoice_is_a_real_pdf(): void
    {
        $o = $this->place();
        $this->actingAs($this->owner)->get('/business/orders/'.$o->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_csv_preview_and_commit_are_validated_and_atomic(): void
    {
        $csv = app(CatalogCsv::class);
        $preview = $csv->preview(public_path('samples/catalog.csv'));
        $this->assertEmpty($preview['errors']);
        $this->assertCount(2, $preview['rows']);
        $this->assertEquals(2, $csv->commit($preview['rows']));
        $this->assertFalse(Product::where('slug', 'classic-cotton-panjabi')->first()->active);
        $again = $csv->preview(public_path('samples/catalog.csv'));
        $this->assertCount(2, $again['errors']);
    }

    public function test_private_and_non_https_import_sources_are_rejected(): void
    {
        foreach (['http://example.com', 'https://127.0.0.1', 'https://localhost', 'https://169.254.169.254/latest', 'https://user:pass@example.com', 'https://example.com:8080'] as $url) {
            try {
                app(SafePageReader::class)->validateTarget($url);
                $this->fail('Unsafe source accepted: '.$url);
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_simulated_courier_cod_and_remittance_reconcile_once(): void
    {
        $order = $this->place();
        foreach (['confirmed', 'processing', 'packed'] as $status) {
            app(Orders::class)->transition($order, $status);
        }
        $courier = app(Courier::class);
        $shipment = $courier->book($order);
        $this->assertSame($shipment->id, $courier->book($order)->id);
        $courier->deliver($shipment);
        $courier->deliver($shipment);
        $courier->remit($shipment, 5000);
        $courier->remit($shipment, 5000);
        $this->assertEquals('remitted', $shipment->fresh()->status);
        $this->assertEquals($order->total - 5000, $shipment->fresh()->remitted);
        $this->assertEquals(0, JournalLine::where('account', 'COD clearing')->sum('debit') - JournalLine::where('account', 'COD clearing')->sum('credit'));
        $this->balanced();
    }

    public function test_attendance_package_simulation_deduplicates_device_events(): void
    {
        $e = Employee::create(['branch_id' => $this->branch->id, 'name' => 'Staff', 'phone' => '01700000000', 'position' => 'Tailor', 'salary' => 2000000, 'joined_at' => '2026-01-01']);
        $service = app(AttendanceImport::class);
        $this->assertSame(2, $service->simulate($e));
        $this->assertSame(0, $service->simulate($e));
        $this->assertEquals(2, Attendance::count());
    }

    public function test_new_screens_and_platform_permissions(): void
    {
        $this->actingAs($this->owner);
        foreach (['/business/ai', '/business/catalog/import', '/business/staff', '/business/settings', '/business/requests', '/business/courier', '/admin/campaigns', '/admin/campaigns/create'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/platform')->assertForbidden();
        $platform = User::create(['name' => 'Platform', 'email' => 'platform@test.example', 'password' => 'password', 'role' => 'platform']);
        app(TenantContext::class)->set(null);
        $this->actingAs($platform)->get('/platform')->assertOk()->assertSee('Test Shop');
    }

    public function test_branch_cashier_cannot_access_other_branch_customer_request(): void
    {
        $o = $this->place();
        $customer = User::create(['organization_id' => $this->org->id, 'name' => 'Customer', 'email' => 'customer@test.example', 'password' => 'password', 'role' => 'customer']);
        $this->actingAs($customer)->post('/shop/test-shop/orders/'.$o->id.'/request', ['type' => 'cancel', 'reason' => 'Please cancel this order'])->assertForbidden();
    }

    public function test_pos_rejects_wrong_payment_total_and_cashier_requires_shift(): void
    {
        $data = ['branch_id' => $this->branch->id, 'customer_name' => 'Walk-in', 'phone' => 'walk-in', 'items' => [$this->variant->id => 1], 'idempotency_key' => (string) Str::uuid(), 'cash' => 100, 'bank' => 0, 'channel' => 'pos'];
        $this->actingAs($this->owner)->post('/business/pos', $data)->assertSessionHasErrors('cash');
        $this->assertEquals(0, Order::count());
        $this->assertEquals(10, StockLevel::first()->quantity);
        $cashier = User::create(['organization_id' => $this->org->id, 'branch_id' => $this->branch->id, 'name' => 'Cashier', 'email' => 'cash@test.example', 'password' => 'password', 'role' => 'cashier']);
        $data['cash'] = 200000;
        $this->actingAs($cashier)->post('/business/pos', $data)->assertSessionHasErrors('shift');
        $this->post('/business/shifts', ['branch_id' => $this->branch->id, 'opening' => 10000])->assertSessionHasNoErrors();
        $this->post('/business/pos', $data)->assertSessionHasNoErrors();
        $this->assertEquals(1, Order::count());
        $shift = Shift::first();
        $this->post('/business/shifts/'.$shift->id.'/close', ['counted' => 210000])->assertSessionHasNoErrors();
        $this->assertEquals(210000, $shift->fresh()->expected);
    }

    public function test_gateway_verifies_signatures_amount_and_repeated_callbacks(): void
    {
        $order = $this->place();
        $gateway = app(DemoGateway::class);
        $attempt = $gateway->initiate($order);
        $body = json_encode(['reference' => $attempt->reference, 'amount' => $attempt->amount, 'status' => 'success']);
        try {
            $gateway->callback($body, 'invalid');
            $this->fail('Signature must be checked.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
        $this->assertEquals(0, Payment::count());
        $gateway->callback($body, $gateway->sign($body));
        $gateway->callback($body, $gateway->sign($body));
        $this->assertEquals(1, Payment::count());
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->balanced();
    }

    public function test_failed_gateway_does_not_collect_payment(): void
    {
        $order = $this->place();
        app(DemoGateway::class)->simulate($order,'failed');
        $this->assertEquals(0,Payment::count());
        $this->assertEquals('failed',PaymentAttempt::first()->status);
    }

    public function test_language_switch_persists_bengali_navigation(): void
    {
        $this->post('/language/bn')->assertRedirect();
        $this->get('/')->assertOk()->assertSee('সুবিধাসমূহ');
    }
}
