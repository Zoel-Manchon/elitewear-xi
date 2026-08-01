<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas HTML.
 *
 * La CSP es la pieza importante: convierte un XSS almacenado en un fallo
 * bloqueado por el navegador. Solo funciona si NO hay JavaScript inline —
 * por eso galería, carrito, buscador y filtros viven en módulos de Vite.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // El mismo nonce se aplica a los assets de Vite y a los bloques JSON-LD.
        // Así mantenemos una CSP estricta sin renunciar a datos estructurados.
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();

        $response = $next($request);

        // Las respuestas JSON no renderizan nada: no necesitan CSP.
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $paypal = 'https://www.paypal.com https://www.sandbox.paypal.com https://*.paypal.com';
        $dev = $this->viteDevOrigins();

        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data: blob: https://*.paypal.com https://www.thesportsdb.com https://r2.thesportsdb.com",
            "font-src 'self' data: https://fonts.bunny.net {$dev['http']}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net {$dev['http']}",
            "script-src 'self' 'nonce-{$nonce}' {$paypal} {$dev['script']}",
            "connect-src 'self' {$paypal} {$dev['http']} {$dev['ws']}",
            "frame-src {$paypal}",
            // El navegador nos manda aquí cada bloqueo: sin esto, una CSP
            // es una política sin telemetría.
            'report-uri '.route('csp.report'),
        ];

        $response->headers->add([
            'Content-Security-Policy' => implode('; ', array_map('trim', $csp)),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ]);

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    /**
     * Orígenes del servidor de desarrollo de Vite.
     *
     * No los escribimos a mano: Vite deja su URL real en public/hot al
     * arrancar, y puede ser localhost, 127.0.0.1 o [::1] según cómo resuelva
     * el sistema. Para una CSP esos tres son orígenes DISTINTOS, así que
     * adivinarlos es garantía de bloquear el propio CSS del proyecto.
     *
     * En producción devuelve cadenas vacías: no hay servidor de desarrollo
     * al que abrirle la puerta.
     *
     * @return array{http: string, ws: string, script: string}
     */
    private function viteDevOrigins(): array
    {
        $empty = ['http' => '', 'ws' => '', 'script' => ''];

        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return $empty;
        }

        $origin = rtrim(trim((string) file_get_contents(public_path('hot'))), '/');

        if ($origin === '') {
            return $empty;
        }

        $ws = str_starts_with($origin, 'https://')
            ? 'wss://'.substr($origin, 8)
            : 'ws://'.substr($origin, 7);

        return [
            'http' => $origin,
            'ws' => $ws,
            // El cliente de HMR de Vite evalúa código: en desarrollo hace
            // falta aflojar aquí, y SOLO aquí.
            // Sin 'unsafe-inline': al haber nonce el navegador lo ignora y
            // avisa por consola. 'unsafe-eval' sí hace falta para el cliente
            // de recarga en caliente de Vite.
            'script' => "{$origin} 'unsafe-eval'",
        ];
    }
}
