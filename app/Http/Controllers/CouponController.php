<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Support\CartResolver;
use App\Support\CouponSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function __construct(
        private readonly CartResolver $cart,
        private readonly CouponSession $coupons,
    ) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $subtotal = $this->cart->current()?->subtotal()->cents ?? 0;

        $coupon = Coupon::where('code', strtoupper(trim($validated['code'])))->first();

        // Mismo mensaje si el código no existe que si existe pero no sirve:
        // lo contrario convierte el formulario en un buscador de cupones.
        if (! $coupon || ($reason = $coupon->rejectionReason($subtotal))) {
            $mensaje = $reason ?? 'Ese código no es válido.';

            // Una petición JSON recibe 422 con el error en el cuerpo; una del
            // formulario, una redirección con la bolsa de errores. Devolver
            // siempre back() dejaba a los clientes JSON con un 302 y sin
            // manera de leer el motivo.
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['code' => $mensaje]);
            }

            return back()->withErrors(['code' => $mensaje]);
        }

        $this->coupons->put($coupon);

        return back()->with('status', "Código aplicado: {$coupon->describe()}.");
    }

    public function destroy(): RedirectResponse
    {
        $this->coupons->forget();

        return back()->with('status', 'Código retirado.');
    }
}
