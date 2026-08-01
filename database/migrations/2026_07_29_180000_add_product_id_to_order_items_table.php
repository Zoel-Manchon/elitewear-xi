<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['order_id', 'product_id']);
        });

        // Conserva la posibilidad de reseñar pedidos creados antes de esta
        // migración. El backfill es deliberadamente portable entre MySQL y
        // SQLite para que la misma migración funcione también en la suite.
        DB::table('order_items')
            ->whereNull('product_id')
            ->whereNotNull('product_variant_id')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $productsByVariant = DB::table('product_variants')
                    ->whereIn('id', $items->pluck('product_variant_id'))
                    ->pluck('product_id', 'id');

                foreach ($items as $item) {
                    $productId = $productsByVariant[$item->product_variant_id] ?? null;

                    if ($productId) {
                        DB::table('order_items')
                            ->where('id', $item->id)
                            ->update(['product_id' => $productId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['order_id', 'product_id']);
            $table->dropColumn('product_id');
        });
    }
};
