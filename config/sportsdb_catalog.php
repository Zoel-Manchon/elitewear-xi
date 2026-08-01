<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Identificadores oficiales de TheSportsDB
    |--------------------------------------------------------------------------
    |
    | El catálogo trabaja con IDs conocidos para evitar búsquedas ambiguas y
    | reducir el consumo de la cuota gratuita a un lookupequipment.php por
    | equipo.
    |
    */
    'teams' => [
        'afc-ajax' => 133772,
        'real-madrid-cf' => 133738,
        'fc-barcelona' => 133739,
        'juventus-fc' => 133676,
        'ac-milan' => 133667,
        'olympique-de-marseille' => 133707,
        'manchester-united' => 133612,
        'liverpool-fc' => 133602,
        'fc-bayern-munchen' => 133664,
        'fc-porto' => 134114,
        'celtic-fc' => 133647,
        'crvena-zvezda' => 133987,
        'boca-juniors' => 135156,
        'club-atletico-river-plate' => 135171,
        'clube-de-regatas-do-flamengo' => 134287,
        'sociedade-esportiva-palmeiras' => 134465,
        'club-atletico-penarol' => 135369,
        'colo-colo' => 137724,
        'club-america' => 134193,
        'atletico-nacional' => 137607,
        'kashima-antlers' => 137707,
        'urawa-red-diamonds' => 137716,
        'al-hilal-sfc' => 136013,
        'persepolis-fc' => 139013,
        'al-ahly-sc' => 138995,
        'raja-club-athletic' => 136404,
        'tp-mazembe' => 138139,
        'seleccion-de-espana' => 133909,
        'seleccion-de-paises-bajos' => 133905,
        'seleccion-de-alemania' => 133907,
        'seleccion-de-italia' => 133910,
        'seleccion-de-dinamarca' => 133906,
        'seleccion-de-croacia' => 133912,
        'seleccion-de-brasil' => 134496,
        'seleccion-de-argentina' => 134509,
        'seleccion-de-mexico' => 134497,
        'seleccion-de-colombia' => 134501,
        'seleccion-de-japon' => 134503,
        'seleccion-de-corea-del-sur' => 134517,
        'seleccion-de-arabia-saudi' => 136137,
        'seleccion-de-nigeria' => 134512,
        'seleccion-de-camerun' => 134498,
        'seleccion-de-sudafrica' => 136482,
        'seleccion-de-marruecos' => 136139,
    ],

    'regions' => [
        'afc-ajax' => 'europa',
        'real-madrid-cf' => 'europa',
        'fc-barcelona' => 'europa',
        'juventus-fc' => 'europa',
        'ac-milan' => 'europa',
        'olympique-de-marseille' => 'europa',
        'manchester-united' => 'europa',
        'liverpool-fc' => 'europa',
        'fc-bayern-munchen' => 'europa',
        'fc-porto' => 'europa',
        'celtic-fc' => 'europa',
        'crvena-zvezda' => 'europa',
        'boca-juniors' => 'america',
        'club-atletico-river-plate' => 'america',
        'clube-de-regatas-do-flamengo' => 'america',
        'sociedade-esportiva-palmeiras' => 'america',
        'club-atletico-penarol' => 'america',
        'colo-colo' => 'america',
        'club-america' => 'america',
        'atletico-nacional' => 'america',
        'kashima-antlers' => 'asia',
        'urawa-red-diamonds' => 'asia',
        'al-hilal-sfc' => 'asia',
        'persepolis-fc' => 'asia',
        'al-ahly-sc' => 'africa',
        'raja-club-athletic' => 'africa',
        'tp-mazembe' => 'africa',
        'seleccion-de-espana' => 'europa',
        'seleccion-de-paises-bajos' => 'europa',
        'seleccion-de-alemania' => 'europa',
        'seleccion-de-italia' => 'europa',
        'seleccion-de-dinamarca' => 'europa',
        'seleccion-de-croacia' => 'europa',
        'seleccion-de-brasil' => 'america',
        'seleccion-de-argentina' => 'america',
        'seleccion-de-mexico' => 'america',
        'seleccion-de-colombia' => 'america',
        'seleccion-de-japon' => 'asia',
        'seleccion-de-corea-del-sur' => 'asia',
        'seleccion-de-arabia-saudi' => 'asia',
        'seleccion-de-nigeria' => 'africa',
        'seleccion-de-camerun' => 'africa',
        'seleccion-de-sudafrica' => 'africa',
        'seleccion-de-marruecos' => 'africa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ligas para ampliar el catálogo con la clave gratuita
    |--------------------------------------------------------------------------
    |
    | search_all_teams.php devuelve hasta 10 equipos por liga en el nivel 123.
    | El comando catalog:sportsdb-full combina esos equipos con los 44 IDs
    | editoriales ya existentes y descarga todo el material de equipaciones
    | que los endpoints gratuitos exponen.
    |
    */
    'discovery_leagues' => [
        ['query' => 'English Premier League', 'region' => 'europa'],
        ['query' => 'English League Championship', 'region' => 'europa'],
        ['query' => 'Spanish La Liga', 'region' => 'europa'],
        ['query' => 'Italian Serie A', 'region' => 'europa'],
        ['query' => 'German Bundesliga', 'region' => 'europa'],
        ['query' => 'French Ligue 1', 'region' => 'europa'],
        ['query' => 'Dutch Eredivisie', 'region' => 'europa'],
        ['query' => 'Portuguese Primeira Liga', 'region' => 'europa'],
        ['query' => 'Scottish Premier League', 'region' => 'europa'],
        ['query' => 'Belgian Pro League', 'region' => 'europa'],
        ['query' => 'Turkish Super Lig', 'region' => 'europa'],
        ['query' => 'Greek Super League', 'region' => 'europa'],
        ['query' => 'Brazilian Serie A', 'region' => 'america'],
        ['query' => 'Argentinian Primera Division', 'region' => 'america'],
        ['query' => 'Mexican Primera League', 'region' => 'america'],
        ['query' => 'American Major League Soccer', 'region' => 'america'],
        ['query' => 'Colombian Primera A', 'region' => 'america'],
        ['query' => 'Japanese J1 League', 'region' => 'asia'],
        ['query' => 'Saudi Pro League', 'region' => 'asia'],
        ['query' => 'Australian A-League', 'region' => 'asia'],
        ['query' => 'South Korean K League 1', 'region' => 'asia'],
        ['query' => 'South African Premier Division', 'region' => 'africa'],
        ['query' => 'Egyptian Premier League', 'region' => 'africa'],
        ['query' => 'Moroccan Botola Pro', 'region' => 'africa'],
    ],

    // 30 peticiones/minuto en el plan gratuito. 2100 ms deja margen.
    'free_delay_ms' => (int) env('THESPORTSDB_FREE_DELAY_MS', 0),

    // El cliente aplica esta pausa antes de cada petición con la clave 123.
    'free_request_interval_ms' => (int) env('THESPORTSDB_FREE_REQUEST_INTERVAL_MS', 2100),

    'default_price_cents' => (int) env('THESPORTSDB_PRODUCT_PRICE_CENTS', 8995),

    'sizes' => [
        'S' => ['price_delta_cents' => 0, 'stock' => 6],
        'M' => ['price_delta_cents' => 0, 'stock' => 10],
        'L' => ['price_delta_cents' => 0, 'stock' => 8],
        'XL' => ['price_delta_cents' => 200, 'stock' => 4],
        'XXL' => ['price_delta_cents' => 400, 'stock' => 2],
    ],
];
