<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('carrier', 80)->nullable()->after('currency');
            $table->string('tracking_number', 120)->nullable()->after('carrier');
            $table->string('tracking_url', 500)->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('paid_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'carrier', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at',
            ]);
        });
    }
};
