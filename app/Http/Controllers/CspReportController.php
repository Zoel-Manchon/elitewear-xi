<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Recoge los informes que envía el navegador cuando la CSP bloquea algo.
 *
 * Es el bucle de realimentación que le falta a casi toda CSP: sin esto no
 * sabes si tu política está parando un ataque, rompiendo tu propia web, o
 * simplemente no está haciendo nada.
 */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // El cuerpo lo controla el cliente: se acota antes de tocarlo.
        $raw = substr($request->getContent(), 0, 8192);
        $report = json_decode($raw, true)['csp-report'] ?? null;

        if (! is_array($report)) {
            return response()->noContent();
        }

        // Las extensiones del navegador generan mucho ruido con esquemas
        // propios. Filtrarlas es lo que hace el registro utilizable.
        $blocked = (string) ($report['blocked-uri'] ?? '');

        foreach (['chrome-extension', 'moz-extension', 'safari-extension', 'about:'] as $ruido) {
            if (str_starts_with($blocked, $ruido)) {
                return response()->noContent();
            }
        }

        Log::channel('stack')->warning('Violación de CSP', [
            'directiva' => $report['violated-directive'] ?? null,
            'bloqueado' => substr($blocked, 0, 300),
            'documento' => substr((string) ($report['document-uri'] ?? ''), 0, 300),
            'ip' => $request->ip(),
        ]);

        return response()->noContent();
    }
}
