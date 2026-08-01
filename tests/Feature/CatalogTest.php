<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Team;

use function Pest\Laravel\get;

it('rechaza una ordenacion inventada antes de llegar a la consulta', function () {
    // Es una ruta web, no de API: una validación fallida redirige con
    // errores en sesión. El 422 es la respuesta de las rutas JSON.
    get('/camisetas?orden=; DROP TABLE products')
        ->assertRedirect()
        ->assertSessionHasErrors('orden');
});

it('devuelve 404 para un producto sin publicar', function () {
    $variant = variant();
    $variant->product->update(['published_at' => null]);

    get("/camisetas/{$variant->product->slug}")->assertNotFound();
});

it('muestra un producto publicado', function () {
    $variant = variant();

    get("/camisetas/{$variant->product->slug}")
        ->assertOk()
        ->assertSee($variant->product->name);
});

it('acepta precios en euros y filtra sobre centimos', function () {
    $cheap = variant();
    $cheap->product->update(['name' => 'Camiseta económica', 'base_price_cents' => 8995]);

    $premium = variant();
    $premium->product->update(['name' => 'Camiseta premium', 'base_price_cents' => 10995]);

    get('/camisetas?precio_min=100')
        ->assertOk()
        ->assertSee('109,95 €')
        ->assertDontSee('89,95 €');
});

it('combina tipo y region en la misma consulta', function () {
    $selection = variant();
    $selection->product->update(['name' => 'España combinada', 'season' => '2024-25']);

    $club = variant();
    $club->product->update(['name' => 'Club europeo', 'season' => '2024-25']);

    $selecciones = Category::firstOrCreate(['slug' => 'selecciones'], ['name' => 'Selecciones']);
    $clubes = Category::firstOrCreate(['slug' => 'clubes'], ['name' => 'Clubes']);
    $europa = Category::firstOrCreate(['slug' => 'europa'], ['name' => 'Europa']);

    $selection->product->categories()->sync([$selecciones->id, $europa->id]);
    $club->product->categories()->sync([$clubes->id, $europa->id]);

    get('/camisetas?tipo=selecciones&region=europa')
        ->assertOk()
        ->assertSee('España combinada')
        ->assertDontSee('Club europeo');
});

it('prioriza la camiseta del Real Madrid en el hero', function () {
    $realMadrid = Team::create([
        'name' => 'Real Madrid CF',
        'slug' => 'real-madrid-cf',
    ]);

    Product::create([
        'team_id' => $realMadrid->id,
        'name' => 'Real Madrid local 2019-2020',
        'slug' => 'real-madrid-local-2019-2020',
        'season' => '2019-2020',
        'kit_type' => 'home',
        'base_price_cents' => 9995,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    $other = variant();
    $other->product->update(['published_at' => now()]);

    get('/')
        ->assertOk()
        ->assertSee('Real Madrid local 2019-2020')
        ->assertSee('hero-real-madrid-2019-2020.png');
});

it('no renderiza tallas superpuestas en las tarjetas del catalogo', function () {
    variant();

    get('/camisetas')
        ->assertOk()
        ->assertDontSee('shirt-card__sizes');
});
