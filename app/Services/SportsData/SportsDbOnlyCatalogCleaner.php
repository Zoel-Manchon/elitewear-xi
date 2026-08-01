<?php

namespace App\Services\SportsData;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\TeamEquipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SportsDbOnlyCatalogCleaner
{
    /** @return array{products_deactivated: int, images_deleted: int, equipment_deleted: int} */
    public function clean(): array
    {
        $images = ProductImage::query()
            ->where(function ($query): void {
                $query->whereNull('source_provider')
                    ->orWhere('source_provider', '!=', 'thesportsdb');
            })
            ->get();

        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        return DB::transaction(function () use ($images): array {
            $imagesDeleted = ProductImage::query()
                ->whereKey($images->pluck('id'))
                ->delete();

            $productsDeactivated = Product::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('team_equipment_id')
                        ->orWhereDoesntHave('teamEquipment', fn ($equipment) => $equipment
                            ->where('provider', 'thesportsdb'));
                })
                ->update([
                    'is_active' => false,
                    'published_at' => null,
                ]);

            $equipmentDeleted = TeamEquipment::query()
                ->where('provider', '!=', 'thesportsdb')
                ->delete();

            return [
                'products_deactivated' => $productsDeactivated,
                'images_deleted' => $imagesDeleted,
                'equipment_deleted' => $equipmentDeleted,
            ];
        });
    }
}
