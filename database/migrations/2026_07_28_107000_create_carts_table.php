<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            // Carrito de invitado: user_id null + token. Al hacer login se fusiona.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('token')->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
