<?php

use App\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('expone el catalogo sin autenticacion', function () {
    variant();

    getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonStructure(['data' => [['slug', 'name', 'price']]]);
});

it('no expone el stock exacto, solo disponibilidad', function () {
    $variant = variant(stock: 7);

    getJson("/api/v1/products/{$variant->product->slug}")
        ->assertOk()
        ->assertJsonPath('data.variants.0.in_stock', true)
        ->assertJsonMissing(['stock' => 7]);
});

it('exige token para los pedidos', function () {
    getJson('/api/v1/orders')->assertUnauthorized();
});

it('emite un token con credenciales validas', function () {
    $user = User::factory()->create(['password' => bcrypt('una-contrasena-larga-99')]);

    postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'una-contrasena-larga-99',
        'device_name' => 'test',
    ])->assertCreated()->assertJsonStructure(['token', 'abilities']);
});

it('no revela si un correo existe', function () {
    $user = User::factory()->create();

    $existe = postJson('/api/v1/tokens', [
        'email' => $user->email, 'password' => 'mal', 'device_name' => 'test',
    ]);

    $noExiste = postJson('/api/v1/tokens', [
        'email' => 'nadie@example.com', 'password' => 'mal', 'device_name' => 'test',
    ]);

    expect($existe->json('errors.email'))->toBe($noExiste->json('errors.email'));
});
