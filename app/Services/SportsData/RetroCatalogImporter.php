<?php

namespace App\Services\SportsData;

use App\Enums\KitType;
use App\Enums\ShirtSize;
use App\Models\Category;
use App\Models\Product;
use App\Models\Team;
use App\Models\TeamEquipment;
use Illuminate\Support\Str;
use Throwable;

final class RetroCatalogImporter
{
    public function __construct(
        private readonly WikimediaCommonsClient $client,
        private readonly WikimediaKitImageImporter $imageImporter,
    ) {}

    /** @param array{team:string,season:string,type:string,file:string} $definition */
    public function import(array $definition, bool $download = true): array
    {
        $team = Team::query()->where('slug', $definition['team'])->first();
        if (! $team) {
            return ['status' => 'Equipo no encontrado', 'created' => 0, 'updated' => 0, 'downloaded' => 0];
        }

        $file = $this->client->file($definition['file']);
        if (! $file) {
            return ['status' => 'Archivo no encontrado', 'created' => 0, 'updated' => 0, 'downloaded' => 0];
        }

        $externalId = substr(sha1(strtolower((string) $file['title'])), 0, 32);
        $equipment = TeamEquipment::query()->updateOrCreate(
            ['provider' => 'wikimedia_commons', 'external_equipment_id' => $externalId],
            [
                'team_id' => $team->id,
                'external_team_id' => $team->slug,
                'source_created_at' => null,
                'season' => $definition['season'],
                'image_url' => $file['image_url'],
                'equipment_type' => $definition['type'],
                'contributor' => $file['author'],
                'raw_payload' => $file,
            ],
        );

        $kitType = KitType::from($definition['type']);
        $slug = Str::slug(implode('-', [
            $team->slug,
            $definition['season'],
            $kitType->value,
            'archivo-retro',
            $externalId,
        ]));

        $product = Product::withTrashed()->where('team_equipment_id', $equipment->id)->first();
        $created = $product === null;

        $attributes = [
            'team_id' => $team->id,
            'team_equipment_id' => $equipment->id,
            'name' => $team->name.' '.$definition['season'].' · '.$kitType->label(),
            'slug' => $slug,
            'season' => $definition['season'],
            'kit_type' => $kitType,
            'description' => $this->description($team, $definition['season'], $kitType, $file),
            'primary_color' => '#1b4d3e',
            'secondary_color' => '#f4f1e8',
            'pattern' => 'solid',
            'shirt_number' => null,
            'base_price_cents' => (int) config('retro_catalog.default_price_cents', 8495),
            'currency' => 'EUR',
            'is_active' => true,
            'published_at' => now(),
        ];

        if ($product) {
            if ($product->trashed()) {
                $product->restore();
            }
            $product->update($attributes);
        } else {
            $product = Product::query()->create($attributes);
        }

        $this->syncVariants($product, $externalId);
        $this->syncCategories($product, $team, $definition['season']);

        $downloaded = 0;
        if ($download) {
            try {
                $this->imageImporter->import($product, $equipment, true);
                $downloaded = 1;
            } catch (Throwable $exception) {
                return [
                    'status' => 'Producto creado; imagen no descargada: '.$exception->getMessage(),
                    'created' => $created ? 1 : 0,
                    'updated' => $created ? 0 : 1,
                    'downloaded' => 0,
                ];
            }
        }

        return [
            'status' => 'OK',
            'created' => $created ? 1 : 0,
            'updated' => $created ? 0 : 1,
            'downloaded' => $downloaded,
        ];
    }

    private function description(Team $team, string $season, KitType $kitType, array $file): string
    {
        $license = trim((string) ($file['license'] ?? ''));
        $licenseText = $license !== '' ? " Licencia de la imagen: {$license}." : '';

        return "Camiseta histórica del {$team->name}, temporada {$season}, {$kitType->label()}. "
            .'La referencia visual procede del archivo libre de Wikimedia Commons; no representa una fotografía del artículo físico concreto.'
            .$licenseText;
    }

    private function syncVariants(Product $product, string $externalId): void
    {
        foreach ((array) config('retro_catalog.sizes', []) as $size => $values) {
            $shirtSize = ShirtSize::from($size);
            $product->variants()->updateOrCreate(
                ['size' => $shirtSize],
                [
                    'sku' => strtoupper(Str::slug("RETRO-{$externalId}-{$shirtSize->value}")),
                    'price_delta_cents' => (int) $values['price_delta_cents'],
                    'stock' => (int) $values['stock'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function syncCategories(Product $product, Team $team, string $season): void
    {
        $ids = [];

        $retro = Category::firstOrCreate(['slug' => 'archivo-retro'], ['name' => 'Archivo retro']);
        $ids[] = $retro->id;

        $scopeSlug = str_starts_with($team->slug, 'seleccion-') ? 'selecciones' : 'clubes';
        $scope = Category::firstOrCreate(
            ['slug' => $scopeSlug],
            ['name' => $scopeSlug === 'selecciones' ? 'Selecciones' : 'Clubes'],
        );
        $ids[] = $scope->id;

        $regionSlug = config("sportsdb_catalog.regions.{$team->slug}");
        if (is_string($regionSlug) && $regionSlug !== '') {
            $region = Category::firstOrCreate(
                ['slug' => $regionSlug],
                ['name' => match ($regionSlug) {
                    'europa' => 'Europa',
                    'america' => 'América',
                    'asia' => 'Asia',
                    'africa' => 'África',
                    default => ucfirst($regionSlug),
                }],
            );
            $ids[] = $region->id;
        }

        $year = (int) substr($season, 0, 4);
        if ($year > 0) {
            $decade = (int) floor($year / 10) * 10;
            $category = Category::firstOrCreate(
                ['slug' => "anos-{$decade}"],
                ['name' => "Años {$decade}"],
            );
            $ids[] = $category->id;
        }

        $product->categories()->sync(array_values(array_unique($ids)));
    }
}
