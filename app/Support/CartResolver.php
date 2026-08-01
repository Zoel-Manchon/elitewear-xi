<?php

namespace App\Support;

use App\Models\Cart;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Decide de qué carrito estamos hablando en esta petición.
 *
 * SEGURIDAD: para invitados, el token vive en la SESIÓN, nunca en un
 * parámetro de request ni en una cookie propia. Si el cliente pudiera
 * elegir el token, cambiar ese valor sería leer y modificar el carrito
 * de otra persona — un IDOR de manual.
 */
class CartResolver
{
    private const SESSION_KEY = 'cart_token';

    private ?Cart $resolved = null;

    public function __construct(
        private readonly Session $session,
        private readonly Auth $auth,
    ) {}

    /** El carrito actual, o null si todavía no existe. Sin efectos secundarios. */
    public function current(): ?Cart
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $query = Cart::query()->with(['items.variant.product.team', 'items.variant.product.primaryImage']);

        if ($user = $this->auth->guard()->user()) {
            return $this->resolved = $query->where('user_id', $user->getAuthIdentifier())->first();
        }

        $token = $this->session->get(self::SESSION_KEY);

        return $this->resolved = $token
            ? $query->where('token', $token)->whereNull('user_id')->first()
            : null;
    }

    /** El carrito actual, creándolo si hace falta. Úsalo solo al escribir. */
    public function currentOrCreate(): Cart
    {
        if ($cart = $this->current()) {
            return $cart;
        }

        if ($user = $this->auth->guard()->user()) {
            $cart = Cart::create(['user_id' => $user->getAuthIdentifier()]);
        } else {
            $token = (string) Str::uuid();
            $cart = Cart::create([
                'token' => $token,
                'expires_at' => now()->addDays(30),
            ]);
            $this->session->put(self::SESSION_KEY, $token);
        }

        return $this->resolved = $cart->load(['items.variant.product.team', 'items.variant.product.primaryImage']);
    }

    public function forgetGuestCart(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->resolved = null;
    }
}
