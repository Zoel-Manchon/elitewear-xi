<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->uuid('token')->unique();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // Un correo no puede apuntarse dos veces a la misma talla.
            $table->unique(['product_variant_id', 'email']);
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
