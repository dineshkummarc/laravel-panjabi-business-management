<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('type')->default('retail');
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->json('modules');
            $t->timestamps();
        });
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('code');
            $t->text('address')->nullable();
            $t->string('phone')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['organization_id', 'code']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('role')->default('customer');
        });
        Schema::create('categories', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('slug');
            $t->unique(['organization_id', 'slug']);
        });
        Schema::create('products', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->string('fabric')->nullable();
            $t->string('fit')->default('Regular');
            $t->string('image')->nullable();
            $t->boolean('active')->default(true);
            $t->boolean('featured')->default(false);
            $t->string('kind')->default('finished');
            $t->unique(['organization_id', 'slug']);
        });
        Schema::create('variants', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('sku');
            $t->string('barcode')->nullable();
            $t->string('size');
            $t->string('color');
            $t->unsignedBigInteger('price');
            $t->unsignedBigInteger('wholesale_price')->default(0);
            $t->unsignedBigInteger('cost');
            $t->unsignedInteger('reorder_level')->default(5);
            $t->unique(['organization_id', 'sku']);
        });
        Schema::create('stock_levels', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->integer('quantity')->default(0);
            $t->unique(['branch_id', 'variant_id']);
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->integer('quantity');
            $t->string('reason');
            $t->string('reference');
            $t->index(['organization_id', 'branch_id', 'created_at']);
        });
        foreach (['customers', 'suppliers'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $this->base($t);
                $t->string('name');
                $t->string('phone');
                $t->string('email')->nullable();
                $t->text('address')->nullable();
            });
        }
        Schema::create('orders', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number')->unique();
            $t->string('token', 64)->unique();
            $t->string('idempotency_key')->unique();
            $t->string('channel');
            $t->string('status')->default('pending');
            $t->string('payment_status')->default('unpaid');
            $t->string('fulfillment_status')->default('unfulfilled');
            $t->string('customer_name');
            $t->string('phone');
            $t->text('address')->nullable();
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('shipping')->default(0);
            $t->unsignedBigInteger('total');
            $t->string('payment_method')->default('cod');
            $t->text('notes')->nullable();
            $t->index(['organization_id', 'status']);
        });
        Schema::create('order_items', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('returned')->default(0);
            $t->unsignedBigInteger('unit_price');
            $t->unsignedBigInteger('unit_cost');
        });
        Schema::create('payments', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->string('reference')->unique();
            $t->string('method');
            $t->bigInteger('amount');
            $t->text('note')->nullable();
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('reference')->unique();
            $t->string('description');
        });
        Schema::create('journal_lines', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $t->string('account');
            $t->unsignedBigInteger('debit')->default(0);
            $t->unsignedBigInteger('credit')->default(0);
        });
        Schema::create('purchases', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('received')->default(0);
            $t->unsignedBigInteger('unit_cost');
            $t->unsignedBigInteger('paid')->default(0);
            $t->string('status')->default('ordered');
            $t->string('reference')->unique();
        });
        Schema::create('transfers', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('source_id')->constrained('branches')->restrictOnDelete();
            $t->foreignId('destination_id')->constrained('branches')->restrictOnDelete();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('received')->default(0);
            $t->string('status')->default('dispatched');
        });
        Schema::create('employees', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('phone');
            $t->string('position');
            $t->unsignedBigInteger('salary');
            $t->date('joined_at');
        });
        Schema::create('attendances', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('event_id');
            $t->string('type');
            $t->timestamp('occurred_at');
            $t->unique(['organization_id', 'event_id']);
        });
        Schema::create('payrolls', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('period');
            $t->unsignedBigInteger('gross');
            $t->unsignedBigInteger('deduction')->default(0);
            $t->unsignedBigInteger('net');
            $t->string('status')->default('paid');
            $t->unique(['employee_id', 'period']);
        });
        Schema::create('tailoring_jobs', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('customer_name');
            $t->string('phone');
            $t->string('design');
            $t->json('measurements');
            $t->date('due_date');
            $t->unsignedBigInteger('price');
            $t->unsignedBigInteger('deposit')->default(0);
            $t->unsignedBigInteger('collected')->default(0);
            $t->string('status')->default('received');
        });
        Schema::create('production_batches', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->foreignId('material_id')->constrained('variants')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('material_quantity');
            $t->unsignedBigInteger('labor_cost')->default(0);
            $t->string('status')->default('planned');
        });
        Schema::create('expenses', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('category');
            $t->string('description');
            $t->unsignedBigInteger('amount');
            $t->date('spent_at');
        });
        Schema::create('shifts', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('opening');
            $t->bigInteger('expected')->nullable();
            $t->unsignedBigInteger('counted')->nullable();
            $t->timestamp('closed_at')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject');
            $t->json('details');
        });
        Schema::create('catalog_drafts', function (Blueprint $t) {
            $this->base($t);
            $t->string('source');
            $t->json('payload');
            $t->string('status')->default('draft');
        });
    }

    private function base(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('organization_id')->constrained()->restrictOnDelete();
        $t->timestamps();
    }

    public function down(): void
    {
        foreach (['catalog_drafts', 'audit_logs', 'shifts', 'expenses', 'production_batches', 'tailoring_jobs', 'payrolls', 'attendances', 'employees', 'transfers', 'purchases', 'journal_lines', 'journal_entries', 'payments', 'order_items', 'orders', 'suppliers', 'customers', 'stock_movements', 'stock_levels', 'variants', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('branch_id');
            $t->dropConstrainedForeignId('organization_id');
            $t->dropColumn('role');
        });
        Schema::dropIfExists('branches');
        Schema::dropIfExists('organizations');
    }
};
