<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirige al login si no hay sesion', function () {
    get('/admin')->assertRedirect('/login');
});

it('devuelve 404 a un usuario normal, no 403', function () {
    actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin')
        ->assertNotFound();
});

it('deja entrar al administrador', function () {
    actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin')
        ->assertOk();
});

it('no permite escalar privilegios desde el registro', function () {
    $this->post('/register', [
        'name' => 'Atacante',
        'email' => 'atacante@example.com',
        'password' => 'una-contrasena-muy-larga-99',
        'password_confirmation' => 'una-contrasena-muy-larga-99',
        'is_admin' => 1,
    ]);

    expect(User::where('email', 'atacante@example.com')->first()?->is_admin)->toBeFalsy();
});
