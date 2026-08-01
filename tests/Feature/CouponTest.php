<?php

use App\Actions\Order\PlaceOrder;
use App\Models\Cart;
use App\Models\User;

use function Pest\Laravel\postJson;

it('aplica un porcentaje sobre el subtotal', function () {
    $coupon = coupon(['type' => 'percent', 'value' => 10]);

    expect($coupon->discountFor(10000))->toBe(1000);
});

it('nunca descuenta mas que el subtotal', function () {
    $coupon = coupon(['type' => 'fixed', 'value' => 999999]);

    // Un total negativo sería regalar dinero.
    expect($coupon->discountFor(5000))->toBe(5000);
});

it('rechaza un codigo caducado', function () {
    $coupon = coupon(['expires_at' => now()->subDay()]);

    expect($coupon->rejectionReason(10000))->not->toBeNull();
});

it('rechaza un codigo agotado', function () {
    $coupon = coupon(['max_redemptions' => 1]);
    $coupon->update(['redemptions_count' => 1]);

    expect($coupon->rejectionReason(10000))->not->toBeNull();
});

it('da el mismo mensaje para un codigo inexistente que para uno invalido', function () {
    coupon(['code' => 'CADUCADO', 'expires_at' => now()->subDay()]);

    // Por JSON en lugar de por sesión: la ruta devuelve 422 con el mensaje
    // en el cuerpo, que es una lectura estable. La bolsa de errores de sesión
    // depende de que la petición se haya redirigido y de cómo se serialice.
    $inexistente = postJson('/carrito/cupon', ['code' => 'NOEXISTE'])
        ->assertStatus(422)->json('errors.code.0');

    $invalido = postJson('/carrito/cupon', ['code' => 'CADUCADO'])
        ->assertStatus(422)->json('errors.code.0');

    expect($inexistente)->toBe($invalido)->not->toBeEmpty();
});

it('incrementa el contador de canjes al crear el pedido', function () {
    $coupon = coupon(['type' => 'percent', 'value' => 10]);
    $variant = variant(stock: 5);
    $user = User::factory()->create();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $order = app(PlaceOrder::class)->handle(
        $cart->load('items.variant.product'),
        ['full_name' => 'Zoel', 'line1' => 'Calle 1', 'city' => 'León', 'postal_code' => '24001', 'country_code' => 'ES'],
        'test@example.com',
        $user->id,
        $coupon,
    );

    expect($coupon->fresh()->redemptions_count)->toBe(1)
        ->and($order->discount_cents)->toBe(899)          // 10% de 8995
        ->and($order->coupon_code)->toBe('RETRO10')
        ->and($order->total_cents)->toBe(8995 - 899 + 495);
});

it('no aplica el descuento si el codigo se agoto entre aplicarlo y pagar', function () {
    $coupon = coupon(['max_redemptions' => 1]);
    $variant = variant(stock: 5);
    $user = User::factory()->create();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    // Otro comprador lo canjea primero.
    $coupon->increment('redemptions_count');

    $order = app(PlaceOrder::class)->handle(
        $cart->load('items.variant.product'),
        ['full_name' => 'Zoel', 'line1' => 'Calle 1', 'city' => 'León', 'postal_code' => '24001', 'country_code' => 'ES'],
        'test@example.com',
        $user->id,
        $coupon,
    );

    // El pedido sale adelante, simplemente sin descuento.
    expect($order->discount_cents)->toBe(0)
        ->and($order->coupon_id)->toBeNull();
});

it('muestra el descuento aplicado en el carrito y el checkout', function () {
    $coupon = coupon(['code' => 'RETRO10', 'type' => 'percent', 'value' => 10]);
    $variant = variant(stock: 5);
    $user = User::factory()->create();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $this->actingAs($user)
        ->withSession(['coupon_code' => $coupon->code])
        ->get('/carrito')
        ->assertOk()
        ->assertSee('−8,99 €', false)
        ->assertSee('RETRO10');

    $this->actingAs($user)
        ->withSession(['coupon_code' => $coupon->code])
        ->get('/checkout')
        ->assertOk()
        ->assertSee('−8,99 €', false)
        ->assertSee('RETRO10');
});
