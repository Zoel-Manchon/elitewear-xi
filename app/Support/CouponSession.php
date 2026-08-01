<?php

namespace App\Support;

use App\Models\Coupon;
use Illuminate\Contracts\Session\Session;

/**
 * El cupón aplicado vive en la sesión, no en el carrito ni en un campo
 * oculto del formulario. Igual que el token del carrito de invitado: si el
 * cliente pudiera elegirlo, se lo aplicaría él mismo saltándose las
 * comprobaciones.
 */
class CouponSession
{
    private const KEY = 'coupon_code';

    public function __construct(private readonly Session $session) {}

    public function current(): ?Coupon
    {
        $code = $this->session->get(self::KEY);

        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            $this->forget();
        }

        return $coupon;
    }

    /** Devuelve el cupón solo si sigue siendo válido para el subtotal actual. */
    public function validFor(int $subtotalCents): ?Coupon
    {
        $coupon = $this->current();

        if (! $coupon || $coupon->rejectionReason($subtotalCents) !== null) {
            $this->forget();

            return null;
        }

        return $coupon;
    }

    public function put(Coupon $coupon): void
    {
        $this->session->put(self::KEY, strtoupper(trim($coupon->code)));
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
