<?php

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function featureOrder(User $user, Product $product, OrderStatus $status = OrderStatus::Paid): Order
{
    $order = Order::create([
        'user_id' => $user->id,
        'number' => 'RS-2026-'.random_int(100000, 999999),
        'status' => $status,
        'email' => $user->email,
        'subtotal_cents' => 8995,
        'shipping_cents' => 495,
        'total_cents' => 9490,
        'currency' => 'EUR',
        'shipping_address' => [
            'full_name' => $user->name,
            'line1' => 'Calle Prueba 1',
            'city' => 'León',
            'postal_code' => '24001',
            'country_code' => 'ES',
        ],
        'placed_at' => now()->subHour(),
        'paid_at' => $status !== OrderStatus::Pending ? now() : null,
    ]);

    $variant = $product->variants()->firstOrFail();
    $order->items()->create([
        'product_variant_id' => $variant->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'team_name' => $product->team->name,
        'season' => $product->season,
        'size' => $variant->size->value,
        'sku' => $variant->sku,
        'unit_price_cents' => 8995,
        'quantity' => 1,
        'line_total_cents' => 8995,
    ]);

    return $order;
}

it('permite completar el checkout sin crear una cuenta', function () {
    $variant = variant(stock: 3);
    $cart = Cart::create([
        'token' => (string) Str::uuid(),
        'expires_at' => now()->addDay(),
    ]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $response = $this->withSession(['cart_token' => $cart->token])->post('/checkout', [
        'full_name' => 'Compra Invitada',
        'email' => 'invitada@example.com',
        'line1' => 'Calle Prueba 1',
        'city' => 'León',
        'province' => 'León',
        'postal_code' => '24001',
        'country_code' => 'ES',
    ]);

    $order = Order::latest('id')->firstOrFail();

    $response->assertRedirect(route('checkout.payment', $order));
    expect($order->user_id)->toBeNull()
        ->and(session('guest_order_access.'.$order->id))->not->toBeNull();
});

it('fusiona favoritos locales con los favoritos de la cuenta', function () {
    $user = User::factory()->create();
    $first = variant()->product;
    $second = variant()->product;
    $user->wishlistProducts()->attach($first->id);

    actingAs($user)
        ->postJson('/favoritos/sincronizar', ['product_ids' => [$second->id]])
        ->assertOk()
        ->assertJsonCount(2, 'data.ids');

    expect($user->wishlistProducts()->pluck('products.id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

it('solo admite reseñas ligadas a una compra pagada', function () {
    $user = User::factory()->create();
    $product = variant()->product;
    $order = featureOrder($user, $product);
    $item = $order->items->first();

    actingAs($user)->post(route('reviews.store', $product), [
        'order_item_id' => $item->id,
        'rating' => 5,
        'title' => 'Muy buena reedición',
        'body' => 'El tejido y el ajuste son mejores de lo que esperaba.',
    ])->assertRedirect();

    expect(Review::count())->toBe(1)
        ->and(Review::first()->is_approved)->toBeTrue();
});

it('rechaza una reseña de un producto no comprado', function () {
    $user = User::factory()->create();
    $bought = variant()->product;
    $other = variant()->product;
    $item = featureOrder($user, $bought)->items->first();

    actingAs($user)->post(route('reviews.store', $other), [
        'order_item_id' => $item->id,
        'rating' => 4,
        'body' => 'Esta reseña no debe poder publicarse porque no compré el producto.',
    ])->assertNotFound();
});

it('permite consultar un pedido con número y correo sin cuenta', function () {
    $user = User::factory()->create();
    $product = variant()->product;
    $order = featureOrder($user, $product, OrderStatus::Shipped);

    post('/seguimiento', [
        'number' => strtolower($order->number),
        'email' => strtoupper($order->email),
    ])->assertRedirect(route('tracking.show', $order));

    get(route('tracking.show', $order))
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee('Enviado');
});

it('publica canonical open graph y datos estructurados de producto', function () {
    $product = variant()->product;

    get(route('products.show', $product))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('products.show', $product).'">', false)
        ->assertSee('application/ld+json', false)
        // Sin JSON_UNESCAPED_SLASHES la barra sale escapada como \/ .
        // Se quitó ese flag a propósito: con él, un "</script>" dentro de un
        // nombre de producto —que viene de TheSportsDB— cerraría el bloque.
        ->assertSee('schema.org', false)
        ->assertSee('og:type', false);
});
