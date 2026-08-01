<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('paypal');
            // El unique es el candado de idempotencia frente a webhooks duplicados.
            $table->string('provider_order_id')->unique();
            $table->string('provider_capture_id')->nullable()->index();
            $table->string('status', 20)->default('created');
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
