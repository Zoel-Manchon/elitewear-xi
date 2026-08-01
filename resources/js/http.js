/**
 * Cliente HTTP mínimo compartido por carrito y buscador.
 * Centralizarlo evita repetir el token CSRF y el manejo de respuestas
 * que no son JSON (419, 500, redirects), que es donde se pierde el tiempo.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function request(url, { method = 'GET', body, signal } = {}) {
    const response = await fetch(url, {
        method,
        signal,
        headers: {
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const text = await response.text();
    let payload;

    try {
        payload = JSON.parse(text);
    } catch {
        throw new Error(`El servidor respondió ${response.status} en vez de JSON.`);
    }

    if (!response.ok) {
        throw new Error(
            payload?.message ??
                Object.values(payload?.errors ?? {})[0]?.[0] ??
                'Algo ha fallado. Inténtalo otra vez.',
        );
    }

    return payload.data ?? payload;
}

/** El contenido viene de la base de datos: escapar cierra un XSS almacenado. */
export function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
