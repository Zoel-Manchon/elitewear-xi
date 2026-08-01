<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 24)->unique();          // RS-2026-000123
            $table->string('status', 20)->default('pending');
            $table->string('email');

            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('shipping_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->char('currency', 3)->default('EUR');

            // Snapshot: si el usuario borra su direccion, el pedido no cambia.
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
