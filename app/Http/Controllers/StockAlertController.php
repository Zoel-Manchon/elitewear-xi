<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAlertRequest;
use App\Models\StockAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    public function store(StoreStockAlertRequest $request): JsonResponse|RedirectResponse
    {
        // Respuesta idéntica exista o no el aviso, y esté o no registrado ese
        // correo. Un mensaje distinto convertiría este formulario en un
        // comprobador de cuentas: escribo un correo y la web me dice si existe.
        StockAlert::firstOrCreate(
            [
                'product_variant_id' => $request->integer('product_variant_id'),
                'email' => $request->string('email')->lower()->toString(),
            ],
            ['user_id' => $request->user()?->id],
        );

        $message = 'Hecho. Te escribiremos en cuanto vuelva esa talla.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    /** Baja mediante URL firmada: el enlace del correo no se puede fabricar. */
    public function destroy(Request $request, string $alert): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        StockAlert::where('token', $alert)->delete();

        return redirect()
            ->route('home')
            ->with('status', 'Aviso cancelado. No volveremos a escribirte por esa talla.');
    }
}
