<?php

namespace App\Services\SportsData;

use App\Enums\KitType;
use App\Enums\ShirtSize;
use App\Models\Category;
use App\Models\Product;
use App\Models\Team;
use App\Models\TeamEquipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

final class SportsDbCatalogProductReconciler
{
    public function __construct(
        private readonly SportsDbKitImageImporter $importer,
    ) {}

    /**
     * Convierte las equipaciones almacenadas de TheSportsDB en productos
     * visibles del catálogo.
     *
     * Los productos asociados a referencias de TheSportsDB que ya no estén
     * presentes pueden desactivarse. Los productos de otros proveedores se
     * conservan.
     *
     * @param  Collection<int, TeamEquipment>  $equipment
     * @return array{
     *     created: int,
     *     updated: int,
     *     deactivated: int,
     *     downloaded: int,
     *     skipped: int,
     *     errors: array<int, string>
     * }
     */
    public function reconcile(
        Team $team,
        Collection $equipment,
        bool $downloadImages = true,
        bool $deactivateStale = true,
    ): array {
        $usable = $equipment
            ->filter(
                fn (TeamEquipment $item): bool => $item->team_id === $team->id
                    && $item->provider === 'thesportsdb'
                    && trim((string) $item->image_url) !== '',
            )
            ->unique('id')
            ->values();

        /** @var Product|null $template */
        $template = Product::withTrashed()
            ->where('team_id', $team->id)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        $activeEquipmentIds = $usable->pluck('id')->all();
        $deactivated = 0;

        if ($deactivateStale) {
            /*
             * Solo se desactivan productos gestionados por esta sincronización:
             * huérfanos o asociados a equipaciones de TheSportsDB.
             *
             * Los productos enlazados a otros proveedores no se modifican.
             */
            $stale = Product::query()
                ->where('team_id', $team->id)
                ->where('is_active', true)
                ->where(
                    fn ($query) => $query
                        ->whereDoesntHave('teamEquipment')
                        ->orWhereHas(
                            'teamEquipment',
                            fn ($equipmentQuery) => $equipmentQuery->where(
                                'provider',
                                'thesportsdb',
                            ),
                        ),
                );

            if ($activeEquipmentIds !== []) {
                $stale->whereNotIn(
                    'team_equipment_id',
                    $activeEquipmentIds,
                );
            }

            $deactivated = $stale->update([
                'is_active' => false,
                'published_at' => null,
            ]);
        }

        if ($usable->isEmpty()) {
            return [
                'created' => 0,
                'updated' => 0,
                'deactivated' => $deactivated,
                'downloaded' => 0,
                'skipped' => 0,
                'errors' => [],
            ];
        }

        $created = 0;
        $updated = 0;
        $downloaded = 0;
        $skipped = 0;

        /** @var array<int, string> $errors */
        $errors = [];

        foreach ($usable as $item) {
            try {
                // Un producto importado pertenece a un equipo concreto.
                // La búsqueda combina team_id y team_equipment_id para impedir
                // que una reconciliación posterior mueva productos entre equipos.
                $product = Product::withTrashed()
                    ->where('team_id', $team->id)
                    ->where('team_equipment_id', $item->id)
                    ->orderBy('id')
                    ->first();

                $kitType = $item->kitType() ?? KitType::Other;
                $season = trim((string) $item->season);
                $seasonValue = $season !== '' ? $season : null;
                $seasonLabel = $season !== ''
                    ? $season
                    : 'Temporada no indicada';

                $attributes = [
                    'team_id' => $team->id,
                    'team_equipment_id' => $item->id,
                    'name' => $this->productName(
                        $team,
                        $seasonLabel,
                        $item,
                    ),
                    'slug' => $this->productSlug(
                        $team,
                        $seasonLabel,
                        $kitType,
                        $item,
                    ),
                    'season' => $seasonValue,
                    'kit_type' => $kitType,
                    'description' => $this->description(
                        $team,
                        $seasonLabel,
                        $item,
                    ),
                    'primary_color' => $team->sportsdb_colour_1
                        ?? $template->primary_color
                        ?? '#1b4d3e',
                    'secondary_color' => $team->sportsdb_colour_2
                        ?? $template->secondary_color
                        ?? '#f4f1e8',
                    'pattern' => $template?->pattern->value
                        ?? 'solid',
                    'shirt_number' => null,
                    'base_price_cents' => $template->base_price_cents
                        ?? (int) config(
                            'sportsdb_catalog.default_price_cents',
                            8995,
                        ),
                    'currency' => $template->currency ?? 'EUR',
                    'is_active' => true,
                    'published_at' => now(),
                ];

                if ($product !== null) {
                    if ($product->trashed()) {
                        $product->restore();
                    }

                    $product->update($attributes);
                    $updated++;
                } else {
                    $product = Product::query()->create($attributes);
                    $created++;
                }

                $this->syncCategories(
                    $product,
                    $team,
                    $seasonValue,
                    $template,
                );

                $this->syncVariants($product, $item);

                if ($downloadImages) {
                    $this->importer->import(
                        $product,
                        $item,
                        true,
                    );

                    $downloaded++;
                }
            } catch (Throwable $exception) {
                $skipped++;
                $errors[] = class_basename($exception)
                    .': '
                    .$exception->getMessage();

                report($exception);
            }
        }

        return compact(
            'created',
            'updated',
            'deactivated',
            'downloaded',
            'skipped',
            'errors',
        );
    }

    private function productName(
        Team $team,
        string $season,
        TeamEquipment $equipment,
    ): string {
        return "{$team->name} {$season} · {$equipment->typeLabel()}";
    }

    private function productSlug(
        Team $team,
        string $season,
        KitType $kitType,
        TeamEquipment $equipment,
    ): string {
        return Str::slug(implode('-', [
            $team->slug,
            $season,
            $kitType->value,
            $equipment->external_equipment_id,
        ]));
    }

    private function description(
        Team $team,
        string $season,
        TeamEquipment $equipment,
    ): string {
        $parts = [
            "Camiseta {$equipment->typeLabel()} del {$team->name}, "
                ."correspondiente a {$season}.",
        ];

        $facts = array_values(array_filter([
            $team->sportsdb_league
                ? "Competición: {$team->sportsdb_league}."
                : null,
            $team->sportsdb_country
                ? "País: {$team->sportsdb_country}."
                : null,
            $team->sportsdb_formed_year
                ? "Club fundado en {$team->sportsdb_formed_year}."
                : null,
            $team->sportsdb_stadium
                ? "Estadio: {$team->sportsdb_stadium}."
                : null,
        ]));

        if ($facts !== []) {
            $parts[] = implode(' ', $facts);
        }

        $context = Str::of(
            (string) $team->sportsDbDescription(),
        )
            ->squish()
            ->limit(420, '…')
            ->toString();

        if ($context !== '') {
            $parts[] = $context;
        }

        $parts[] = 'Imagen y metadatos obtenidos mediante la API oficial '
            .'de TheSportsDB.';

        return implode(' ', $parts);
    }

    private function syncVariants(
        Product $product,
        TeamEquipment $equipment,
    ): void {
        /**
         * @var array<
         *     string,
         *     array{price_delta_cents: int, stock: int}
         * > $sizes
         */
        $sizes = config('sportsdb_catalog.sizes', []);

        foreach ($sizes as $size => $values) {
            $shirtSize = ShirtSize::from($size);

            $sku = strtoupper(Str::slug(implode('-', [
                'TSDB',
                $equipment->external_equipment_id,
                $shirtSize->value,
            ])));

            $product->variants()->updateOrCreate(
                ['size' => $shirtSize],
                [
                    'sku' => $sku,
                    'price_delta_cents' => (int) $values['price_delta_cents'],
                    'stock' => (int) $values['stock'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function syncCategories(
        Product $product,
        Team $team,
        ?string $season,
        ?Product $template,
    ): void {
        $categoryIds = $template?->categories()
            ->where('categories.slug', 'not like', 'anos-%')
            ->pluck('categories.id')
            ->all() ?? [];

        $scopeSlug = str_starts_with(
            $team->slug,
            'seleccion-',
        )
            ? 'selecciones'
            : 'clubes';

        $scope = Category::firstOrCreate(
            ['slug' => $scopeSlug],
            [
                'name' => $scopeSlug === 'selecciones'
                    ? 'Selecciones'
                    : 'Clubes',
            ],
        );

        $categoryIds[] = $scope->id;

        $regionSlug = $team->sportsdb_region
            ?: config("sportsdb_catalog.regions.{$team->slug}");

        if (is_string($regionSlug) && $regionSlug !== '') {
            $regionNames = [
                'europa' => 'Europa',
                'america' => 'América',
                'asia' => 'Asia',
                'africa' => 'África',
            ];

            $region = Category::firstOrCreate(
                ['slug' => $regionSlug],
                [
                    'name' => $regionNames[$regionSlug]
                        ?? ucfirst($regionSlug),
                ],
            );

            $categoryIds[] = $region->id;
        }

        $year = $season !== null
            ? (int) substr($season, 0, 4)
            : 0;

        if ($year > 0) {
            $decade = (int) floor($year / 10) * 10;

            $decadeCategory = Category::firstOrCreate(
                ['slug' => "anos-{$decade}"],
                ['name' => "Años {$decade}"],
            );

            $categoryIds[] = $decadeCategory->id;
        }

        $product->categories()->sync(
            array_values(array_unique($categoryIds)),
        );
    }
}
