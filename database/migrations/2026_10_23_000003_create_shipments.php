<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $t->string('provider')->default('demo');
            $t->string('reference')->unique();
            $t->string('status')->default('booked');
            $t->unsignedBigInteger('cod_amount')->default(0);
            $t->unsignedBigInteger('remitted')->default(0);
            $t->unsignedBigInteger('fee')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
