import { request, escapeHtml } from './http';

/**
 * Buscador en tiempo real.
 *
 * Tres detalles que separan un buscador usable de uno molesto:
 * debounce para no disparar una petición por tecla, AbortController para
 * que una respuesta lenta no pise a una más reciente, y navegación
 * completa con teclado.
 */

const overlay = document.getElementById('searchOverlay');
const input = document.getElementById('searchInput');
const results = document.getElementById('searchResults');

if (overlay && input && results) {
    let timer;
    let controller;
    let cursor = -1;

    const open = () => {
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        input.focus();
        input.select();
    };

    const close = () => {
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
        cursor = -1;
    };

    const hits = () => [...results.querySelectorAll('.search-hit')];

    const move = (delta) => {
        const list = hits();
        if (!list.length) return;

        cursor = (cursor + delta + list.length) % list.length;

        list.forEach((hit, index) => {
            const on = index === cursor;
            hit.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) hit.scrollIntoView({ block: 'nearest' });
        });
    };

    /** Resalta el término dentro del resultado, sin inyectar HTML del usuario. */
    const highlight = (text, term) => {
        const safe = escapeHtml(text);
        if (!term) return safe;

        const pattern = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return safe.replace(new RegExp(`(${pattern})`, 'gi'), '<mark>$1</mark>');
    };

    const skeleton = () => {
        results.innerHTML = Array.from({ length: 3 })
            .map(
                () => `
        <div class="search-hit">
            <div class="skeleton" style="width:48px;height:60px"></div>
            <div class="w-100">
                <div class="skeleton mb-2" style="height:12px;width:38%"></div>
                <div class="skeleton" style="height:14px;width:66%"></div>
            </div>
        </div>`,
            )
            .join('');
    };

    const render = (payload, term) => {
        cursor = -1;

        if (!payload.results.length) {
            results.innerHTML = `
            <div class="text-center py-5">
                <p class="mb-2">Ninguna camiseta coincide con <strong>${escapeHtml(term)}</strong>.</p>
                <p class="search-hint mb-0">Prueba con un equipo, una temporada o un país.</p>
            </div>`;
            return;
        }

        results.innerHTML = payload.results
            .map(
                (hit) => `
        <a class="search-hit" href="${hit.url}" aria-selected="false">
            <img src="${hit.image}" alt="" loading="lazy">
            <div>
                <div class="shirt-card__team">${highlight(hit.team, term)} · ${escapeHtml(hit.season ?? '')}</div>
                <div class="fw-semibold">${highlight(hit.name, term)}</div>
            </div>
            <div class="price">${hit.price}</div>
        </a>`,
            )
            .join('');
    };

    const search = (term) => {
        controller?.abort();
        controller = new AbortController();

        request(`/buscar?q=${encodeURIComponent(term)}`, { signal: controller.signal })
            .then((payload) => render(payload, term))
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    results.innerHTML =
                        '<p class="text-center py-4 search-hint">No se pudo buscar. Inténtalo otra vez.</p>';
                }
            });
    };

    input.addEventListener('input', () => {
        const term = input.value.trim();
        clearTimeout(timer);

        if (term.length < 2) {
            results.innerHTML =
                '<p class="text-center py-4 search-hint">Escribe al menos dos letras</p>';
            return;
        }

        skeleton();
        timer = setTimeout(() => search(term), 220);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(-1);
        } else if (event.key === 'Enter' && cursor >= 0) {
            event.preventDefault();
            hits()[cursor]?.click();
        }
    });

    document.querySelectorAll('[data-search-open]').forEach((button) => {
        button.addEventListener('click', open);
    });

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay || event.target.closest('[data-search-close]')) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
            close();
        }

        // Atajo de teclado: "/" abre el buscador, como en GitHub.
        if (
            event.key === '/' &&
            !overlay.classList.contains('is-open') &&
            !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)
        ) {
            event.preventDefault();
            open();
        }
    });
}
