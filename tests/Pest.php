<?php

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // El CSRF se desactiva en TestCase::setUp(), no aquí.
    ->in('Feature');

// Unit también necesita la aplicación arrancada: instanciar un modelo
// Eloquent o llamar a config() sin contenedor lanza un error.
// Sin RefreshDatabase: estos tests no deben tocar la base de datos.
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
|--------------------------------------------------------------------------
| Helpers compartidos
|--------------------------------------------------------------------------
|
| Van AQUÍ y no dentro de un fichero de test: Pest incluye los ficheros de
| Feature en orden alfabético, así que una función declarada en
| CartSecurityTest no existe todavía cuando se cargan ApiTest o
| AuditLogTest. Pest.php se carga siempre primero.
|
*/

function variant(int $stock = 5): ProductVariant
{
    $team = Team::create([
        'name' => 'Test FC',
        'slug' => 'test-fc-'.Str::random(8),
    ]);

    $product = Product::create([
        'team_id' => $team->id,
        'name' => 'Camiseta de prueba',
        'slug' => 'camiseta-'.Str::random(8),
        'season' => '1994-95',
        'kit_type' => 'home',
        'base_price_cents' => 8995,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    return $product->variants()->create([
        'size' => 'M',
        'sku' => 'SKU-'.Str::random(10),
        'stock' => $stock,
    ]);
}

function coupon(array $attributes = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => 'RETRO10',
        'type' => 'percent',
        'value' => 10,
        'is_active' => true,
    ], $attributes));
}

/**
 * PNG real para los fakes de Http::fake().
 *
 * Los tests de importación devolvían 'fake-png-body' como cuerpo de la
 * respuesta. RemoteImageStore lo rechaza, y con razón: verifica que los bytes
 * sean una imagen de verdad antes de guardarla. El test fallaba porque el
 * control de seguridad funcionaba, no porque el código estuviera roto.
 */
function fakePngBody(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAEklEQVR4nGP4z8DwHxkzkC4AADxA'
        .'H+HggXe0AAAAAElFTkSuQmCC'
    );
}
