<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TailoringJob;
use App\Models\User;
use App\Models\Variant;
use App\Services\Inventory;
use App\Services\Ledger;
use App\Services\Operations;
use App\Services\Orders;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') || ! config('business.demo')) {
            throw new \RuntimeException('Demo seeding requires DEMO_MODE=true and a nonproduction environment.');
        }
        DB::transaction(function () {
            $this->business('aarong-demo', 'ঐতিহ্য · Oitijjho', true);
            $this->business('noor-demo', 'Noor Online', false);
            $this->extras();
        });
        app(TenantContext::class)->set(null);
    }

    private function extras(): void
    {
        User::firstOrCreate(['email' => 'platform@panjabi.demo'], ['name' => 'Platform Administrator', 'password' => 'DemoOnly!2026', 'role' => 'platform']);
        $org = Organization::where('slug', 'aarong-demo')->firstOrFail();
        app(TenantContext::class)->set($org->id);
        Campaign::firstOrCreate(['slug' => 'eid-edit'], ['name' => 'The Eid Edit', 'headline' => 'For moments that become memories.', 'description' => 'Discover refined embroidery, comfortable cotton and timeless festive colors. A fictional collection for exploring your demo storefront.', 'active' => true]);
        $org->update(['modules' => array_keys(config('business.modules'))]);
        if (! $org->delivery_policy) {
            $org->update(['delivery_policy' => 'Demo delivery policy: a flat delivery fee applies. Orders are manually confirmed by staff. This demonstration does not dispatch real goods.', 'return_policy' => 'Demo return policy: contact the branch to request a return. Staff must receive and inspect goods before recording a refund. No real purchase is created in this demonstration.']);
        }
    }

    private function business(string $slug, string $name, bool $full): void
    {
        if (Organization::where('slug', $slug)->exists()) {
            return;
        }
        $org = Organization::create(['name' => $name, 'slug' => $slug, 'type' => $full ? 'manufacturer' : 'online', 'phone' => '01700000000', 'address' => 'Bangladesh', 'modules' => $full ? array_keys(config('business.modules')) : ['inventory', 'orders', 'storefront', 'accounting', 'crm']]);
        app(TenantContext::class)->set($org->id);
        $branches = [];
        foreach ($full ? ['Dhaka Flagship', 'Chattogram Studio', 'Sylhet Boutique'] : ['Online Fulfillment'] as $i => $branchName) {
            $branches[] = Branch::create(['name' => $branchName, 'code' => ['DHK', 'CTG', 'SYL'][$i], 'address' => ['Dhanmondi 27, Dhaka', 'GEC Circle, Chattogram', 'Zindabazar, Sylhet'][$i], 'phone' => '0170000000'.$i]);
        }
        $roles = $full ? ['owner', 'manager', 'cashier', 'inventory', 'accountant', 'production', 'hr', 'customer'] : ['seller'];
        $hash = Hash::make('DemoOnly!2026');
        foreach ($roles as $role) {
            User::create(['organization_id' => $org->id, 'branch_id' => in_array($role, ['manager', 'cashier']) ? $branches[0]->id : null, 'name' => ucfirst($role).' Demo', 'email' => $role.'@panjabi.demo', 'password' => $hash, 'role' => $role === 'seller' ? 'owner' : $role, 'email_verified_at' => now()]);
        }
        $categories = [];
        foreach (['Everyday Classics', 'Eid Signature', 'Heritage Embroidery', 'Premium Cotton', 'Raw Materials'] as $i => $category) {
            $categories[] = Category::create(['name' => $category, 'slug' => Str::slug($category)]);
        }
        $colors = ['sage', 'ivory', 'navy', 'maroon', 'olive', 'black'];
        $titles = ['Sage Heritage', 'Ivory Noor', 'Midnight Classic', 'Maroon Nakshi', 'Olive Everyday', 'Black Signature', 'Eid Elegance', 'Bengal Loom', 'Dhanmondi Edit', 'Royal Jamdani', 'Morning Cotton', 'Sylhet Special', 'Chattogram Classic', 'Pearl Embroidery', 'Urban Kurta', 'Festive Gold', 'Soft Linen', 'Traditional White', 'Navy Handloom', 'Rustic Olive', 'Monsoon Cotton', 'Friday Classic', 'Noor Premium', 'Heritage Stitch', 'শুভ্র পাঞ্জাবি', 'নকশি পাঞ্জাবি', 'Eid Crescent', 'Artisan Weave', 'Summer Essential', 'Celebration Panjabi'];
        $variants = [];
        foreach (array_slice($titles, 0, $full ? 30 : 8) as $i => $title) {
            $product = Product::create(['category_id' => $categories[$i % 4]->id, 'name' => $title, 'slug' => universal_slug($title), 'description' => 'A thoughtfully crafted Panjabi with comfortable fabric and considered details. Designed for everyday moments and meaningful celebrations. বাংলাদেশের ঐতিহ্যে তৈরি।', 'fabric' => ['Premium cotton', 'Linen blend', 'Handloom cotton'][$i % 3], 'fit' => $i % 2 ? 'Slim' : 'Regular', 'image' => '/images/panjabi-'.$colors[$i % 6].'.svg', 'featured' => $i % 5 === 0]);
            foreach (['38', '40', '42', '44'] as $j => $size) {
                $variant = Variant::create(['product_id' => $product->id, 'sku' => ($full ? 'OT' : 'NR').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT).'-'.$size, 'barcode' => ($full ? '100' : '200').str_pad((string) ($i * 4 + $j), 8, '0', STR_PAD_LEFT), 'size' => $size, 'color' => ucfirst($colors[$i % 6]), 'price' => ($i % 6 * 350 + 1800) * 100, 'wholesale_price' => ($i % 6 * 300 + 1500) * 100, 'cost' => ($i % 6 * 180 + 950) * 100]);
                $variants[] = $variant;
                foreach ($branches as $k => $branch) {
                    $qty = ($i + $j + $k) % 9 === 0 ? 3 : 15 + ($i + $j) % 12;
                    app(Inventory::class)->move($branch->id, $variant->id, $qty, 'opening', 'OPEN-'.$variant->id.'-'.$branch->id);
                    app(Ledger::class)->pair($branch->id, 'opening:'.$variant->id.':'.$branch->id, 'Opening stock', 'Inventory', 'Opening equity', $qty * $variant->cost);
                }
            }
        }
        foreach (['Dhaka Textile Mills', 'Bengal Embroidery House', 'Noor Packaging'] as $i => $supplier) {
            Supplier::create(['name' => $supplier, 'phone' => '0180000000'.$i, 'address' => 'Dhaka, Bangladesh']);
        }
        foreach (['Rahim Ahmed', 'Karim Hossain', 'Nasir Uddin', 'Fahim Khan', 'Sajid Hasan'] as $i => $customer) {
            Customer::create(['name' => $customer, 'phone' => '0190000000'.$i, 'address' => 'Dhaka, Bangladesh']);
        }
        $orders = app(Orders::class);
        for ($i = 0; $i < ($full ? 20 : 5); $i++) {
            $branch = $branches[$i % count($branches)];
            $v = $variants[$i];
            $order = $orders->place(['branch_id' => $branch->id, 'customer_name' => ['Rahim Ahmed', 'Karim Hossain', 'Sajid Hasan'][$i % 3], 'phone' => '0190000000'.($i % 5), 'address' => 'House 12, Road 4, Dhaka', 'channel' => $i % 3 === 0 ? 'pos' : 'web'], [$v->id => 1], 'seed:'.$org->id.':'.$i);
            if ($i % 3 === 0) {
                $orders->pay($order, $order->total, 'cash', 'seed-pay:'.$order->id);
            } elseif ($i % 3 === 1) {
                foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $status) {
                    $orders->transition($order, $status);
                    $order->refresh();
                } $orders->pay($order, $order->total, 'bank', 'seed-pay:'.$order->id);
                $orders->transition($order->refresh(), 'completed');
            }
            if ($i === 0) {
                $orders->returnItem($order->refresh(), $order->items()->first()->id, 1, 'sellable', 'seed-return:'.$order->id);
            }
            if ($i === 2) {
                $orders->transition($order, 'canceled');
            }
        }
        $customerUser = User::where('email', 'customer@panjabi.demo')->first();
        if ($full && $customerUser) {
            Order::where('channel', 'web')->first()?->update(['user_id' => $customerUser->id]);
        }
        if (! $full) {
            return;
        }
        $ops = app(Operations::class);
        $supplier = Supplier::first();
        $po = Purchase::create(['branch_id' => $branches[0]->id, 'supplier_id' => $supplier->id, 'variant_id' => $variants[0]->id, 'quantity' => 20, 'unit_cost' => $variants[0]->cost, 'reference' => 'PO-DEMO-001']);
        $ops->receive($po, 12);
        $ops->paySupplier($po->refresh(), $variants[0]->cost * 6);
        $transfer = $ops->dispatch($branches[0]->id, $branches[1]->id, $variants[5]->id, 5);
        $ops->receiveTransfer($transfer, 2);
        $raw = Product::create(['category_id' => $categories[4]->id, 'name' => 'Premium Cotton Fabric', 'slug' => 'premium-cotton-fabric', 'kind' => 'material', 'fabric' => 'Cotton', 'description' => 'Base unit: one meter.', 'active' => false]);
        $material = Variant::create(['product_id' => $raw->id, 'sku' => 'FAB-COT-001', 'size' => 'Meter', 'color' => 'Natural', 'cost' => 22000, 'price' => 0]);
        app(Inventory::class)->move($branches[0]->id, $material->id, 500, 'opening', 'OPEN-FABRIC');
        app(Ledger::class)->pair($branches[0]->id, 'opening:fabric', 'Opening fabric', 'Inventory', 'Opening equity', 11000000);
        $batch = ProductionBatch::create(['branch_id' => $branches[0]->id, 'name' => 'Eid Cotton Batch 01', 'variant_id' => $variants[0]->id, 'material_id' => $material->id, 'quantity' => 10, 'material_quantity' => 30, 'labor_cost' => 200000]);
        $ops->produce($batch);
        ProductionBatch::create(['branch_id' => $branches[0]->id, 'name' => 'Eid Cotton Batch 02', 'variant_id' => $variants[4]->id, 'material_id' => $material->id, 'quantity' => 20, 'material_quantity' => 60, 'labor_cost' => 400000]);
        foreach (['Abdul Karim', 'Sadia Rahman', 'Hasan Mahmud', 'Nusrat Jahan'] as $i => $employeeName) {
            $employee = Employee::create(['branch_id' => $branches[$i % 3]->id, 'name' => $employeeName, 'phone' => '0160000000'.$i, 'position' => ['Sales associate', 'Tailor', 'Production operator', 'Accounts assistant'][$i], 'salary' => (18000 + $i * 3000) * 100, 'joined_at' => now()->subYear()->toDateString()]);
            Attendance::create(['employee_id' => $employee->id, 'event_id' => 'seed-att:'.$employee->id, 'type' => 'in', 'occurred_at' => now()->startOfDay()->addHours(3)]);
            $ops->payroll($employee, now()->subMonth()->format('Y-m'), 0);
        }
        foreach (['Fahim Khan', 'Nasir Uddin'] as $i => $customer) {
            $job = TailoringJob::create(['branch_id' => $branches[0]->id, 'customer_name' => $customer, 'phone' => '0171111111'.$i, 'design' => 'Custom embroidered cotton Panjabi', 'measurements' => ['chest' => 42, 'length' => 44, 'sleeve' => 25], 'due_date' => now()->addDays(5 + $i)->toDateString(), 'price' => 350000, 'deposit' => 150000]);
            $ops->tailoringPayment($job, 150000);
            if ($i === 0) {
                $ops->tailoringStatus($job,'cutting');
            }
        }
        $ops->expense(['branch_id' => $branches[0]->id, 'category' => 'Rent', 'description' => 'Showroom monthly rent', 'amount' => 4500000, 'spent_at' => now()->toDateString()]);
    }
}
