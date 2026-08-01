<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

/**
 * Estos tests protegen los filtros de colección que siguen disponibles mediante
 * enlaces directos. La antigua barra lateral se eliminó, pero las URLs de
 * clubes, selecciones, regiones y equipos continúan siendo parte del catálogo.
 * El bug original se escribía como `$except !== 'tipo' && ($filters['tipo'] ?? null)`, y `&&`
 * en PHP devuelve un booleano. El closure recibía `true` en lugar del slug,
 * así que la consulta comparaba `slug = 1`. Sobrevivió a tres revisiones
 * porque ningún test comprobaba que un filtro DEVUELVA lo correcto —
 * solo que la página cargara.
 */
function camiseta(string $equipo, string $season, array $categorias = []): Product
{
    $team = Team::firstOrCreate(
        ['slug' => Str::slug($equipo)],
        ['name' => $equipo],
    );

    $product = Product::create([
        'team_id' => $team->id,
        'name' => "{$equipo} {$season}",
        'slug' => Str::slug("{$equipo} {$season}"),
        'season' => $season,
        'kit_type' => 'home',
        'base_price_cents' => 8995,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    foreach ($categorias as $slug) {
        $product->categories()->attach(
            Category::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id
        );
    }

    return $product;
}

it('filtra por tipo devolviendo solo lo que corresponde', function () {
    camiseta('Ajax', '1994-95', ['clubes', 'europa']);
    camiseta('Selección de Brasil', '1982', ['selecciones', 'america']);

    get('/camisetas?tipo=selecciones')
        ->assertOk()
        ->assertSee('Selección de Brasil')
        ->assertDontSee('Ajax 1994-95');
});

it('filtra por region', function () {
    camiseta('Ajax', '1994-95', ['clubes', 'europa']);
    camiseta('Boca Juniors', '1981', ['clubes', 'america']);

    get('/camisetas?region=america')
        ->assertOk()
        ->assertSee('Boca Juniors')
        ->assertDontSee('Ajax 1994-95');
});

it('cruza dos filtros a la vez', function () {
    camiseta('Ajax', '1994-95', ['clubes', 'europa']);
    camiseta('Selección de Italia', '1994', ['selecciones', 'europa']);
    camiseta('Selección de Brasil', '1982', ['selecciones', 'america']);

    // selecciones Y europa: solo Italia cumple las dos.
    get('/camisetas?tipo=selecciones&region=europa')
        ->assertOk()
        ->assertSee('Selección de Italia')
        ->assertDontSee('Selección de Brasil')
        ->assertDontSee('Ajax 1994-95');
});

it('filtra por equipo', function () {
    camiseta('Ajax', '1994-95');
    camiseta('Liverpool', '1985-86');

    get('/camisetas?equipo=ajax')
        ->assertOk()
        ->assertSee('Ajax 1994-95')
        ->assertDontSee('Liverpool 1985-86');
});

it('combina filtro y precio', function () {
    camiseta('Ajax', '1994-95', ['clubes'])->update(['base_price_cents' => 6000]);
    camiseta('Liverpool', '1985-86', ['clubes'])->update(['base_price_cents' => 15000]);

    get('/camisetas?tipo=clubes&precio_max=100')
        ->assertOk()
        ->assertSee('Ajax 1994-95')
        ->assertDontSee('Liverpool 1985-86');
});

it('permite seleccionar varios tipos con logica OR dentro de la misma faceta', function () {
    camiseta('Ajax', '1994-95', ['clubes', 'europa']);
    camiseta('Selección de Brasil', '1982', ['selecciones', 'america']);

    get(route('products.index', ['tipo' => ['clubes', 'selecciones']]))
        ->assertOk()
        ->assertSee('Ajax')
        ->assertSee('Selección de Brasil');
});

it('permite seleccionar varios continentes y cruzarlos con el tipo', function () {
    camiseta('Ajax', '1994-95', ['clubes', 'europa']);
    camiseta('Boca Juniors', '1981', ['clubes', 'america']);
    camiseta('Selección de Japón', '1998', ['selecciones', 'asia']);

    get(route('products.index', [
        'tipo' => ['clubes'],
        'region' => ['europa', 'america'],
    ]))
        ->assertOk()
        ->assertSee('Ajax')
        ->assertSee('Boca Juniors')
        ->assertDontSee('Selección de Japón');
});

it('filtra por varios paises al mismo tiempo', function () {
    $spain = camiseta('Real Madrid CF', '2019-2020', ['clubes', 'europa']);
    $spain->team->update(['country_code' => 'ES', 'sportsdb_country' => 'Spain']);

    $argentina = camiseta('Boca Juniors', '2024', ['clubes', 'america']);
    $argentina->team->update(['country_code' => 'AR', 'sportsdb_country' => 'Argentina']);

    $japan = camiseta('Kashima Antlers', '2024', ['clubes', 'asia']);
    $japan->team->update(['country_code' => 'JP', 'sportsdb_country' => 'Japan']);

    get(route('products.index', ['pais' => ['ES', 'AR']]))
        ->assertOk()
        ->assertSee('Real Madrid CF')
        ->assertSee('Boca Juniors')
        ->assertDontSee('Kashima Antlers');
});

it('renderiza controles multiseleccion con mejora progresiva', function () {
    $product = camiseta('Real Madrid CF', '2019-2020', ['clubes', 'europa']);
    $product->team->update(['country_code' => 'ES', 'sportsdb_country' => 'Spain']);

    get('/camisetas')
        ->assertOk()
        ->assertSee('name="tipo[]"', false)
        ->assertSee('name="region[]"', false)
        ->assertSee('name="pais[]"', false)
        ->assertSee('data-catalog-filter-form', false)
        ->assertSee('data-catalog-results', false);
});
