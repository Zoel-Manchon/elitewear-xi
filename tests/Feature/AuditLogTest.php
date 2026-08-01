<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('registra quien cambia un precio y cual era el valor anterior', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    actingAs($admin);

    $variant = variant();
    $variant->product->update(['base_price_cents' => 12000]);

    $log = AuditLog::where('action', 'updated')
        ->where('auditable_type', Product::class)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->changes['base_price_cents']['antes'])->toBe(8995)
        ->and($log->changes['base_price_cents']['despues'])->toBe(12000);
});

it('conserva el nombre del autor aunque se borre la cuenta', function () {
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Temporal']);
    actingAs($admin);

    $variant = variant();
    $variant->product->update(['name' => 'Otro nombre']);

    $admin->delete();

    $log = AuditLog::latest('id')->first();

    expect($log->user_id)->toBeNull()
        ->and($log->actor_label)->toBe('Admin Temporal');
});

it('no registra nada cuando el guardado no cambia ningun valor', function () {
    actingAs(User::factory()->create(['is_admin' => true]));

    $variant = variant();
    $antes = AuditLog::count();

    $variant->product->update(['name' => $variant->product->name]);

    expect(AuditLog::count())->toBe($antes);
});
