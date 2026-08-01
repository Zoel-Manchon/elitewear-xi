<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('type', 12)->default('percent');   // percent | fixed
            $table->unsignedInteger('value');                 // 10 = 10% | 1000 = 10,00 €
            $table->unsignedBigInteger('min_subtotal_cents')->default(0);

            // null = ilimitado. El contador se incrementa dentro de la misma
            // transacción que crea el pedido, con la fila bloqueada.
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->string('coupon_code', 32)->nullable()->after('coupon_id');
            $table->unsignedBigInteger('discount_cents')->default(0)->after('subtotal_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'discount_cents']);
        });

        Schema::dropIfExists('coupons');
    }
};
