<?php

use App\Models\StockAlert;
use App\Notifications\BackInStock;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

it('registra un aviso para una talla agotada', function () {
    $variant = variant(stock: 0);

    postJson('/avisos', [
        'product_variant_id' => $variant->id,
        'email' => 'coleccionista@example.com',
    ])->assertOk();

    expect(StockAlert::where('email', 'coleccionista@example.com')->exists())->toBeTrue();
});

it('no duplica el aviso si se apunta dos veces', function () {
    $variant = variant(stock: 0);

    foreach (range(1, 2) as $ignored) {
        postJson('/avisos', [
            'product_variant_id' => $variant->id,
            'email' => 'coleccionista@example.com',
        ])->assertOk();
    }

    expect(StockAlert::count())->toBe(1);
});

it('responde igual exista o no la cuenta, para no filtrar correos', function () {
    $variant = variant(stock: 0);

    $a = postJson('/avisos', ['product_variant_id' => $variant->id, 'email' => 'nadie@example.com']);
    $b = postJson('/avisos', ['product_variant_id' => $variant->id, 'email' => 'otro@example.com']);

    expect($a->json('message'))->toBe($b->json('message'));
});

it('avisa cuando la talla pasa de agotada a disponible', function () {
    Notification::fake();

    $variant = variant(stock: 0);
    StockAlert::create([
        'product_variant_id' => $variant->id,
        'email' => 'coleccionista@example.com',
    ]);

    $variant->update(['stock' => 4]);

    Notification::assertSentTimes(BackInStock::class, 1);
    expect(StockAlert::first()->notified_at)->not->toBeNull();
});

it('no reenvia el aviso si el stock solo sube desde un valor positivo', function () {
    Notification::fake();

    $variant = variant(stock: 2);
    StockAlert::create([
        'product_variant_id' => $variant->id,
        'email' => 'coleccionista@example.com',
    ]);

    $variant->update(['stock' => 9]);

    Notification::assertNothingSent();
});

it('rechaza la baja sin firma valida', function () {
    $variant = variant(stock: 0);
    $alert = StockAlert::create([
        'product_variant_id' => $variant->id,
        'email' => 'coleccionista@example.com',
    ]);

    post("/avisos/{$alert->token}/baja");

    $this->get("/avisos/{$alert->token}/baja")->assertForbidden();
    expect(StockAlert::count())->toBe(1);
});
