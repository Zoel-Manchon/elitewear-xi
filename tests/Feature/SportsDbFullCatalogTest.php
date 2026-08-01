<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Team;
use App\Models\TeamEquipment;
use App\Services\SportsData\CatalogSportsDbSync;
use App\Services\SportsData\SportsDbCatalogProductReconciler;
use App\Services\SportsData\SportsDbOnlyCatalogCleaner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('descubre equipos, guarda su perfil y crea todas las imágenes de equipación disponibles', function () {
    Http::fake([
        '*/search_all_teams.php*' => Http::response([
            'teams' => [[
                'idTeam' => '900001',
                'strTeam' => 'Retro Test FC',
                'strTeamShort' => 'RTF',
                'strTeamAlternate' => 'Retro Test Football Club',
                'intFormedYear' => '1901',
                'strSport' => 'Soccer',
                'strLeague' => 'Test League',
                'idLeague' => '9999',
                'strCountry' => 'Spain',
                'strStadium' => 'Retro Ground',
                'intStadiumCapacity' => '25000',
                'strLocation' => 'Madrid, Spain',
                'strKeywords' => 'The Archive',
                'strWebsite' => 'retro.test',
                'strDescriptionES' => 'Club histórico de prueba.',
                'strDescriptionEN' => 'Historic test club.',
                'strBadge' => 'https://r2.thesportsdb.com/images/test/badge.png',
                'strLogo' => 'https://r2.thesportsdb.com/images/test/logo.png',
                'strBanner' => 'https://r2.thesportsdb.com/images/test/banner.png',
                'strFanart1' => 'https://r2.thesportsdb.com/images/test/fanart.jpg',
                'strEquipment' => 'https://r2.thesportsdb.com/images/test/profile-kit.png',
                'strColour1' => '#112233',
                'strColour2' => 'FFFFFF',
                'strColour3' => '',
            ]],
        ]),
        '*/lookupequipment.php*' => Http::response([
            'equipment' => [[
                'idEquipment' => '700001',
                'idTeam' => '900001',
                'date' => '2024-01-15',
                'strSeason' => '1998-1999',
                'strEquipment' => 'https://r2.thesportsdb.com/images/test/retro-kit.png',
                'strType' => '1st',
                'strUsername' => 'cataloguer',
            ]],
        ]),
    ]);

    $this->artisan('catalog:sportsdb-full', [
        '--league' => ['Test League'],
        '--no-download' => true,
        '--force' => true,
    ])->assertSuccessful();

    $team = Team::query()->where('sportsdb_id', 900001)->firstOrFail();

    expect($team->sportsdb_short_name)->toBe('RTF')
        ->and($team->sportsdb_formed_year)->toBe(1901)
        ->and($team->sportsdb_league)->toBe('Test League')
        ->and($team->sportsdb_stadium)->toBe('Retro Ground')
        ->and($team->sportsdb_description_es)->toBe('Club histórico de prueba.')
        ->and($team->sportsdb_colour_2)->toBe('#FFFFFF')
        ->and($team->equipment()->count())->toBe(2)
        ->and($team->products()->published()->count())->toBe(2);

    expect(TeamEquipment::query()->where('external_equipment_id', '700001')->exists())->toBeTrue()
        ->and(TeamEquipment::query()->where('external_equipment_id', 'like', 'profile-%')->exists())->toBeTrue();
});

it('acumula referencias de TheSportsDB vistas en sincronizaciones anteriores', function () {
    $team = Team::query()->create([
        'name' => 'Accumulator FC',
        'slug' => 'accumulator-fc',
        'sportsdb_id' => 900002,
    ]);

    TeamEquipment::query()->create([
        'team_id' => $team->id,
        'provider' => 'thesportsdb',
        'external_equipment_id' => 'old-1',
        'external_team_id' => '900002',
        'season' => '1988-1989',
        'image_url' => 'https://r2.thesportsdb.com/images/test/old.png',
        'equipment_type' => '1st',
        'raw_payload' => [],
    ]);

    Http::fake([
        '*/lookupteam.php*' => Http::response([
            'teams' => [[
                'idTeam' => '900002',
                'strTeam' => 'Accumulator FC',
                'strSport' => 'Soccer',
                'strCountry' => 'England',
                'strEquipment' => null,
            ]],
        ]),
        '*/lookupequipment.php*' => Http::response([
            'equipment' => [[
                'idEquipment' => 'new-1',
                'idTeam' => '900002',
                'date' => null,
                'strSeason' => '1999-2000',
                'strEquipment' => 'https://r2.thesportsdb.com/images/test/new.png',
                'strType' => '2nd',
                'strUsername' => null,
            ]],
        ]),
    ]);

    $result = app(CatalogSportsDbSync::class)->syncKnownTeam($team, false);
    $stats = app(SportsDbCatalogProductReconciler::class)->reconcile(
        $result['team'],
        $result['equipment'],
        downloadImages: false,
        deactivateStale: true,
    );

    expect($result['equipment'])->toHaveCount(2)
        ->and($stats['created'])->toBe(2)
        ->and(Product::published()->count())->toBe(2);
});

it('retira productos e imágenes de proveedores anteriores', function () {
    Storage::fake('public');

    $team = Team::query()->create(['name' => 'Old Source FC', 'slug' => 'old-source-fc']);
    $equipment = TeamEquipment::query()->create([
        'team_id' => $team->id,
        'provider' => 'legacy_catalog',
        'external_equipment_id' => 'legacy-old',
        'external_team_id' => 'old-source-fc',
        'season' => '1990-91',
        'image_url' => 'https://example.test/old.png',
        'equipment_type' => 'home',
        'raw_payload' => [],
    ]);
    $product = Product::query()->create([
        'team_id' => $team->id,
        'team_equipment_id' => $equipment->id,
        'name' => 'Old Source FC 1990-91',
        'slug' => 'old-source-fc-1990-91',
        'season' => '1990-91',
        'kit_type' => 'home',
        'base_price_cents' => 8995,
        'currency' => 'EUR',
        'is_active' => true,
        'published_at' => now(),
    ]);

    Storage::disk('public')->put('products/old.png', 'old');
    ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => 'products/old.png',
        'is_primary' => true,
        'source_provider' => 'legacy_catalog',
    ]);

    $stats = app(SportsDbOnlyCatalogCleaner::class)->clean();

    expect($stats['products_deactivated'])->toBe(1)
        ->and($stats['images_deleted'])->toBe(1)
        ->and($stats['equipment_deleted'])->toBe(1)
        ->and($product->fresh()->is_active)->toBeFalse()
        ->and(ProductImage::query()->count())->toBe(0)
        ->and(TeamEquipment::query()->count())->toBe(0);

    Storage::disk('public')->assertMissing('products/old.png');
});

it('puede descubrir todos los países disponibles respetando el límite solicitado', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'all_countries.php')) {
            return Http::response([
                'countries' => [
                    ['name_en' => 'Spain'],
                    ['name_en' => 'England'],
                ],
            ]);
        }

        if (str_contains($request->url(), 'search_all_teams.php')) {
            return Http::response([
                'teams' => [[
                    'idTeam' => '900003',
                    'strTeam' => 'Country Discovery FC',
                    'strSport' => 'Soccer',
                    'strCountry' => 'Spain',
                    'strLeague' => 'Spanish Test League',
                    'strDescriptionEN' => 'Country discovery test team.',
                    'strEquipment' => null,
                ]],
            ]);
        }

        if (str_contains($request->url(), 'lookupequipment.php')) {
            return Http::response([
                'equipment' => [[
                    'idEquipment' => '700003',
                    'idTeam' => '900003',
                    'date' => null,
                    'strSeason' => '2001-2002',
                    'strEquipment' => 'https://r2.thesportsdb.com/images/test/country-kit.png',
                    'strType' => '1st',
                    'strUsername' => null,
                ]],
            ]);
        }

        return Http::response(['teams' => null]);
    });

    $this->artisan('catalog:sportsdb-full', [
        '--all-countries' => true,
        '--max-countries' => 1,
        '--no-download' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(Team::query()->where('sportsdb_id', 900003)->exists())->toBeTrue()
        ->and(Product::query()->published()->count())->toBe(1);
});

it('restaura equipaciones de TheSportsDB sin desactivar productos existentes', function () {
    $team = Team::query()->create([
        'name' => 'Restore FC',
        'slug' => 'restore-fc',
        'sportsdb_id' => 900010,
    ]);

    $legacy = Product::query()->create([
        'team_id' => $team->id,
        'name' => 'Producto conservado',
        'slug' => 'producto-conservado',
        'season' => '1980-81',
        'kit_type' => 'home',
        'base_price_cents' => 8995,
        'currency' => 'EUR',
        'is_active' => true,
        'published_at' => now(),
    ]);

    Http::fake([
        '*/lookupteam.php*' => Http::response([
            'teams' => [[
                'idTeam' => '900010',
                'strTeam' => 'Restore FC',
                'strSport' => 'Soccer',
                'strCountry' => 'Spain',
                'strEquipment' => null,
            ]],
        ]),
        '*/lookupequipment.php*' => Http::response([
            'equipment' => [
                [
                    'idEquipment' => 'restore-1',
                    'idTeam' => '900010',
                    'strSeason' => '1990-1991',
                    'strEquipment' => 'https://r2.thesportsdb.com/images/test/restore-1.png',
                    'strType' => '1st',
                ],
                [
                    'idEquipment' => 'restore-2',
                    'idTeam' => '900010',
                    'strSeason' => '1991-1992',
                    'strEquipment' => 'https://r2.thesportsdb.com/images/test/restore-2.png',
                    'strType' => '2nd',
                ],
            ],
        ]),
    ]);

    $this->artisan('catalog:sportsdb-restore', [
        '--team' => 'restore-fc',
        '--no-download' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect($legacy->fresh()->is_active)->toBeTrue()
        ->and($team->products()->published()->count())->toBe(3)
        ->and($team->equipment()->count())->toBe(2);
});
