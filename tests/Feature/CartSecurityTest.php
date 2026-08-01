<?php

use App\Models\Cart;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('no deja tocar la linea del carrito de otra persona', function () {
    $victima = User::factory()->create();
    $cart = Cart::create(['user_id' => $victima->id]);
    $item = $cart->items()->create(['product_variant_id' => variant()->id, 'quantity' => 1]);

    // Un atacante autenticado con OTRA cuenta adivina el id de la linea.
    actingAs(User::factory()->create())
        ->deleteJson("/carrito/items/{$item->id}")
        ->assertNotFound();

    expect($item->fresh())->not->toBeNull();
});

it('rechaza cantidades por encima del tope', function () {
    postJson('/carrito/items', [
        'product_variant_id' => variant()->id,
        'quantity' => 99,
    ])->assertStatus(422);
});

it('rechaza mas unidades de las que hay en stock', function () {
    postJson('/carrito/items', [
        'product_variant_id' => variant(stock: 2)->id,
        'quantity' => 3,
    ])->assertStatus(422);
});

it('rechaza variantes inactivas', function () {
    $variant = variant();
    $variant->update(['is_active' => false]);

    postJson('/carrito/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertStatus(422);
});
