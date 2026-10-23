<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('delivery_fee')->default(12000);
            $t->text('delivery_policy')->nullable();
            $t->text('return_policy')->nullable();
        });
        Schema::table('products', function (Blueprint $t) {
            $t->string('photo_path')->nullable();
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('headline');
            $t->text('description');
            $t->boolean('active')->default(false);
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'slug']);
        });
        Schema::create('customer_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('type');
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_requests');
        Schema::dropIfExists('campaigns');
        Schema::table('payments', fn (Blueprint $t) => $t->dropConstrainedForeignId('shift_id'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('photo_path'));
        Schema::table('organizations', fn (Blueprint $t) => $t->dropColumn(['active', 'delivery_fee', 'delivery_policy', 'return_policy']));
    }
};
