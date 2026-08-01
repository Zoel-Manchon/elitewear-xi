<?php

namespace App\Listeners;

use App\Actions\Cart\MergeGuestCart;
use App\Models\Cart;
use App\Support\CartResolver;
use Illuminate\Auth\Events\Login;

class MergeGuestCartOnLogin
{
    public function __construct(
        private readonly CartResolver $resolver,
        private readonly MergeGuestCart $merge,
    ) {}

    public function handle(Login $event): void
    {
        // Ojo con el orden: en este punto el usuario YA está autenticado,
        // así que hay que leer el carrito de invitado por su token de sesión
        // antes de que el resolver empiece a devolver el del usuario.
        $guestCart = Cart::query()
            ->with('items.variant')
            ->whereNull('user_id')
            ->where('token', session('cart_token'))
            ->first();

        if (! $guestCart) {
            return;
        }

        $this->merge->handle($guestCart, $event->user->getAuthIdentifier());
        $this->resolver->forgetGuestCart();
    }
}
