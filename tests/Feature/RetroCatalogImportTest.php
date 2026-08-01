<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Team;
use App\Models\TeamEquipment;
use App\Services\SportsData\SportsDbCatalogProductReconciler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('mantiene un archivo retro amplio y sin combinaciones duplicadas', function () {
    $shirts = collect(config('retro_catalog.shirts'));

    expect($shirts->count())->toBeGreaterThanOrEqual(100)
        ->and($shirts->pluck('team')->unique()->count())->toBeGreaterThanOrEqual(20)
        ->and($shirts->map(fn (array $shirt) => implode('|', [
            $shirt['team'],
            $shirt['season'],
            $shirt['type'],
        ]))->duplicates()->all())->toBe([]);
});

it('importa una camiseta retro con licencia, imagen y variantes', function () {
    Storage::fake('public');

    Team::query()->create([
        'name' => 'Real Madrid CF',
        'slug' => 'real-madrid-cf',
    ]);

    config()->set('retro_catalog.shirts', [[
        'team' => 'real-madrid-cf',
        'season' => '1990-91',
        'type' => 'home',
        'file' => 'Kit body rmcf9091h.png',
    ]]);

    Http::fake([
        'commons.wikimedia.org/*' => Http::response([
            'query' => [
                'pages' => [[
                    'pageid' => 123,
                    'title' => 'File:Kit body rmcf9091h.png',
                    'imageinfo' => [[
                        'url' => 'https://upload.wikimedia.org/test/rmcf9091h.png',
                        'thumburl' => 'https://upload.wikimedia.org/test/1200px-rmcf9091h.png',
                        'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Kit_body_rmcf9091h.png',
                        'mime' => 'image/png',
                        'width' => 38,
                        'height' => 59,
                        'extmetadata' => [
                            'Artist' => ['value' => 'Autor de prueba'],
                            'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                            'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/'],
                            'ImageDescription' => ['value' => 'Real Madrid 1990-91 home kit'],
                        ],
                    ]],
                ]],
            ],
        ]),
        'upload.wikimedia.org/*' => Http::response(fakePngBody(), 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $this->artisan('catalog:retro-import', [
        '--team' => 'real-madrid-cf',
        '--force' => true,
    ])->assertSuccessful();

    $equipment = TeamEquipment::query()->firstOrFail();
    $product = Product::query()->firstOrFail();
    $image = ProductImage::query()->firstOrFail();

    expect($equipment->provider)->toBe('wikimedia_commons')
        ->and($equipment->raw_payload['license'])->toBe('CC BY-SA 4.0')
        ->and($product->season)->toBe('1990-91')
        ->and($product->is_active)->toBeTrue()
        ->and($product->variants()->count())->toBe(5)
        ->and($image->is_primary)->toBeTrue()
        ->and($image->source_credit)->toBe('Autor de prueba');

    Storage::disk('public')->assertExists($image->path);
});

it('no desactiva el archivo retro al reconstruir TheSportsDB', function () {
    $team = Team::query()->create([
        'name' => 'Ajax',
        'slug' => 'afc-ajax',
    ]);

    $equipment = TeamEquipment::query()->create([
        'team_id' => $team->id,
        'provider' => 'wikimedia_commons',
        'external_equipment_id' => 'commons-test-id',
        'external_team_id' => $team->slug,
        'season' => '1994-95',
        'image_url' => 'https://upload.wikimedia.org/test/ajax.png',
        'equipment_type' => 'home',
        'raw_payload' => [],
    ]);

    $product = Product::query()->create([
        'team_id' => $team->id,
        'team_equipment_id' => $equipment->id,
        'name' => 'Ajax 1994-95 · Local',
        'slug' => 'ajax-1994-95-local-commons',
        'season' => '1994-95',
        'kit_type' => 'home',
        'base_price_cents' => 8495,
        'currency' => 'EUR',
        'is_active' => true,
        'published_at' => now(),
    ]);

    app(SportsDbCatalogProductReconciler::class)->reconcile(
        $team,
        new Collection,
        downloadImages: false,
        deactivateStale: true,
    );

    expect($product->fresh()->is_active)->toBeTrue()
        ->and($product->fresh()->published_at)->not->toBeNull();
});
