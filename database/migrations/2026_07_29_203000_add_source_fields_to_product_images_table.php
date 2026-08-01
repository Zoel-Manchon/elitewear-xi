<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('source_provider', 32)->nullable()->after('is_primary');
            $table->string('source_external_id', 64)->nullable()->after('source_provider');
            $table->text('source_url')->nullable()->after('source_external_id');
            $table->string('source_credit', 120)->nullable()->after('source_url');
            $table->timestamp('downloaded_at')->nullable()->after('source_credit');

            $table->index(['source_provider', 'source_external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['source_provider', 'source_external_id']);
            $table->dropColumn([
                'source_provider',
                'source_external_id',
                'source_url',
                'source_credit',
                'downloaded_at',
            ]);
        });
    }
};
