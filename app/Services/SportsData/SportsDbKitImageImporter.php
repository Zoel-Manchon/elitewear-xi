<?php

namespace App\Services\SportsData;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\TeamEquipment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SportsDbKitImageImporter
{
    public function __construct(private readonly TheSportsDbClient $client,
        private readonly RemoteImageStore $images) {}

    public function import(Product $product, TeamEquipment $equipment, bool $replacePrimary = false): ProductImage
    {
        if ($equipment->provider !== 'thesportsdb') {
            throw new RuntimeException('Solo se admiten imágenes procedentes de TheSportsDB.');
        }

        if ($equipment->team_id !== $product->team_id) {
            throw new RuntimeException('La equipación no pertenece al equipo del producto.');
        }

        /** @var ProductImage|null $existing */
        $existing = ProductImage::query()
            ->where('product_id', $product->id)
            ->where('source_provider', $equipment->provider)
            ->where('source_external_id', $equipment->external_equipment_id)
            ->first();

        if ($existing) {
            $product->update(['team_equipment_id' => $equipment->id]);

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
            directory: 'products/imported',
            slug: $product->slug.'-sportsdb',
            externalId: (string) $equipment->external_equipment_id,
            bytes: $download['body'],
        );

        return DB::transaction(function () use ($product, $equipment, $replacePrimary, $path): ProductImage {
            $makePrimary = $replacePrimary || ! ProductImage::query()
                ->where('product_id', $product->id)
                ->exists();

            if ($makePrimary) {
                ProductImage::query()->where('product_id', $product->id)->update(['is_primary' => false]);
            }

            $position = $makePrimary
                ? 0
                : ((int) ProductImage::query()->where('product_id', $product->id)->max('position')) + 1;

            $image = ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'alt' => "{$product->name}, imagen de equipación",
                'position' => $position,
                'is_primary' => $makePrimary,
                'source_provider' => $equipment->provider,
                'source_external_id' => $equipment->external_equipment_id,
                'source_url' => $equipment->image_url,
                'source_credit' => $equipment->contributor,
                'downloaded_at' => now(),
            ]);

            $product->update(['team_equipment_id' => $equipment->id]);

            return $image;
        });
    }

    /**
     * Importa varias equipaciones del mismo equipo como galería del producto.
     *
     * lookupequipment.php devuelve TODAS las equipaciones registradas de un
     * club, no solo una. Antes se guardaba la primera y el resto se
     * descartaba, así que la ficha se quedaba con una sola foto.
     *
     * Prioriza las de la temporada del producto y completa con las más
     * recientes del mismo equipo.
     *
     * @return int imágenes nuevas añadidas
     */
    public function importGallery(Product $product, int $max = 2): int
    {
        // El tipo declarado dice que team nunca es null, pero en la práctica
        // la relación puede no estar cargada. loadMissing lo garantiza y de
        // paso elimina la comprobación que PHPStan marcaba como imposible.
        $product->loadMissing('team');

        if ($max < 1) {
            return 0;
        }

        // ProductImage ya registra el origen: no volvemos a bajar la misma.
        $yaImportadas = $product->images()
            ->where('source_provider', 'thesportsdb')
            ->whereNotNull('source_external_id')
            ->pluck('source_external_id')
            ->all();

        $candidatas = $product->team->equipment()
            ->where('provider', 'thesportsdb')
            ->whereNotNull('image_url')
            ->when($yaImportadas !== [], fn ($q) => $q->whereNotIn('external_equipment_id', $yaImportadas))
            ->orderByRaw('CASE WHEN season = ? THEN 0 ELSE 1 END', [(string) $product->season])
            ->orderByDesc('season')
            ->limit($max)
            ->get();

        $anadidas = 0;

        /** @var TeamEquipment $equipment */
        foreach ($candidatas as $indice => $equipment) {
            try {
                $this->import(
                    $product,
                    $equipment,
                    // Solo asciende a principal si el producto no tenía imagen.
                    replacePrimary: $indice === 0 && $product->images()->count() === 0,
                );
                $anadidas++;
            } catch (\Throwable $e) {
                // Una URL caída no debe tumbar la galería completa.
                report($e);
            }
        }

        return $anadidas;
    }
}
