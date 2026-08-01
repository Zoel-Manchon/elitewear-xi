<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('season', 9)->nullable();          // '1994-95'
            $table->string('kit_type', 20)->default('home');  // App\Enums\KitType
            $table->text('description')->nullable();

            // Dinero SIEMPRE en enteros (centimos). Nunca float/double.
            $table->unsignedBigInteger('base_price_cents');
            $table->char('currency', 3)->default('EUR');

            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'published_at']);
            $table->index('season');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
