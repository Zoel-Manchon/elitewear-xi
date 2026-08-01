<?php

use App\Enums\KitType;
use App\Models\Product;
use App\Models\TeamEquipment;
use App\Services\SportsData\EquipmentMatcher;
use App\Services\SportsData\TheSportsDbClient;
use Illuminate\Support\Collection;

it('normaliza temporadas cortas y largas para poder cruzarlas', function () {
    $matcher = new EquipmentMatcher;

    expect($matcher->normalizeSeason('1994-95'))->toBe('1994-1995')
        ->and($matcher->normalizeSeason('1999-00'))->toBe('1999-2000')
        ->and($matcher->normalizeSeason('2019/2020'))->toBe('2019-2020')
        ->and($matcher->normalizeSeason('1986'))->toBe('1986');
});

it('convierte los tipos de TheSportsDB en tipos del catálogo', function () {
    expect((new TeamEquipment(['equipment_type' => '1st']))->kitType())->toBe(KitType::Home)
        ->and((new TeamEquipment(['equipment_type' => '2nd']))->kitType())->toBe(KitType::Away)
        ->and((new TeamEquipment(['equipment_type' => '3rd']))->kitType())->toBe(KitType::Third)
        ->and((new TeamEquipment(['equipment_type' => 'Goalkeeper']))->kitType())->toBe(KitType::Goalkeeper)
        ->and((new TeamEquipment(['equipment_type' => 'Special']))->kitType())->toBe(KitType::Other);
});

it('elige la equipación con temporada y tipo exactos', function () {
    $product = new Product([
        'season' => '1994-95',
        'kit_type' => KitType::Home,
    ]);

    $away = new TeamEquipment([
        'season' => '1994-1995',
        'equipment_type' => '2nd',
    ]);

    $home = new TeamEquipment([
        'season' => '1994-1995',
        'equipment_type' => '1st',
    ]);

    $match = (new EquipmentMatcher)->bestFor($product, new Collection([$away, $home]));

    expect($match)->toBe($home);
});

it('solo permite descargar imágenes desde dominios de TheSportsDB', function () {
    $client = new TheSportsDbClient;

    expect($client->isAllowedImageUrl('https://r2.thesportsdb.com/images/media/team/equipment/test.png'))->toBeTrue()
        ->and($client->isAllowedImageUrl('https://www.thesportsdb.com/images/test.png'))->toBeTrue()
        ->and($client->isAllowedImageUrl('http://r2.thesportsdb.com/test.png'))->toBeFalse()
        ->and($client->isAllowedImageUrl('https://example.com/test.png'))->toBeFalse();
});
