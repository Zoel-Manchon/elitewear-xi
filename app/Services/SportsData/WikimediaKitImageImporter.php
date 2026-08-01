<?php

namespace App\Services\SportsData;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\TeamEquipment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WikimediaKitImageImporter
{
    public function __construct(private readonly WikimediaCommonsClient $client,
        private readonly RemoteImageStore $images) {}

    public function import(Product $product, TeamEquipment $equipment, bool $replacePrimary = true): ProductImage
    {
        if ($equipment->provider !== 'wikimedia_commons' || $equipment->team_id !== $product->team_id) {
            throw new RuntimeException('La referencia de Wikimedia no pertenece a este producto.');
        }

        $existing = ProductImage::query()
            ->where('product_id', $product->id)
            ->where('source_provider', $equipment->provider)
            ->where('source_external_id', $equipment->external_equipment_id)
            ->first();

        if ($existing) {
            if ($replacePrimary && ! $existing->is_primary) {
                DB::transaction(function () use ($product, $existing): void {
                    ProductImage::query()->where('product_id', $product->id)->update(['is_primary' => false]);
                    $existing->update(['is_primary' => true, 'position' => 0]);
                });
            }

            return ProductImage::query()->findOrFail($existing->id);
        }

        $download = $this->client->download($equipment->image_url);
        // El id externo llega de la API: nunca se concatena sin filtrar.
        // RemoteImageStore lo sanea, verifica que los bytes sean una imagen
        // real y reencodea a WEBP antes de escribir nada en disco.
        $path = $this->images->put(
            directory: 'products/retro',
            slug: $product->slug.'-commons',
            externalId: (string) $equipment->external_equipment_id,
            bytes: $download['body'],
        );

        return DB::transaction(function () use ($product, $equipment, $replacePrimary, $path): ProductImage {
            $makePrimary = $replacePrimary || ! $product->images()->exists();
            if ($makePrimary) {
                ProductImage::query()->where('product_id', $product->id)->update(['is_primary' => false]);
            }

            $raw = $equipment->raw_payload ?? [];

            return ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'alt' => $product->name.' · archivo histórico',
                'position' => $makePrimary ? 0 : ((int) $product->images()->max('position')) + 1,
                'is_primary' => $makePrimary,
                'source_provider' => 'wikimedia_commons',
                'source_external_id' => $equipment->external_equipment_id,
                'source_url' => $raw['description_url'] ?? $equipment->image_url,
                'source_credit' => $equipment->contributor,
                'downloaded_at' => now(),
            ]);
        });
    }
}
