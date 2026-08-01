<?php

use App\Actions\Order\PlaceOrder;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\User;

it('descuenta stock y congela los precios en el pedido', function () {
    $variant = variant(stock: 3);
    $user = User::factory()->create();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

    $order = app(PlaceOrder::class)->handle(
        $cart->load('items.variant.product'),
        ['full_name' => 'Zoel', 'line1' => 'Calle 1', 'city' => 'León', 'postal_code' => '24001', 'country_code' => 'ES'],
        'test@example.com',
        $user->id,
    );

    expect($variant->fresh()->stock)->toBe(1)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->number)->toStartWith('RS-')
        ->and($order->items->first()->unit_price_cents)->toBe(8995);

    // El snapshot no cambia aunque cambie el catalogo.
    $variant->product->update(['base_price_cents' => 12000]);
    expect($order->items->first()->fresh()->unit_price_cents)->toBe(8995);
});

it('no crea el pedido si no hay stock suficiente', function () {
    $variant = variant(stock: 1);
    $user = User::factory()->create();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

    expect(fn () => app(PlaceOrder::class)->handle(
        $cart->load('items.variant.product'),
        ['full_name' => 'Zoel', 'line1' => 'Calle 1', 'city' => 'León', 'postal_code' => '24001', 'country_code' => 'ES'],
        'test@example.com',
        $user->id,
    ))->toThrow(InsufficientStockException::class);

    // La transaccion revierte: el stock sigue intacto.
    expect($variant->fresh()->stock)->toBe(1);
});
