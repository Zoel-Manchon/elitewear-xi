/**
 * Filtros facetados del catálogo.
 *
 * El formulario sigue funcionando con GET sin JavaScript. Con JS activo,
 * cada cambio solicita el HTML ya renderizado por Laravel y sustituye solo
 * los resultados y las etiquetas activas. No se construye HTML con datos
 * externos en el navegador.
 */

const page = document.querySelector('[data-catalog-page]');
const form = document.querySelector('[data-catalog-filter-form]');

if (page && form) {
    let requestController = null;
    let debounceTimer = null;

    const status = () => document.querySelector('[data-catalog-status]');
    const results = () => document.querySelector('[data-catalog-results]');

    function normaliseText(value) {
        return String(value)
            .normalize('NFD')
            .replace(/\p{Diacritic}/gu, '')
            .toLocaleLowerCase('es-ES')
            .trim();
    }

    function formUrl() {
        const url = new URL(form.action, window.location.origin);
        const params = new URLSearchParams();

        for (const [name, rawValue] of new FormData(form).entries()) {
            const value = String(rawValue).trim();
            if (value !== '') params.append(name, value);
        }

        url.search = params.toString();
        return url;
    }

    function selectedFilterCount() {
        const filterNames = new Set(['tipo[]', 'region[]', 'pais[]', 'equipo', 'q', 'precio_min', 'precio_max']);
        let count = 0;

        for (const control of form.elements) {
            if (!control.name || !filterNames.has(control.name)) continue;

            if (control.type === 'checkbox' || control.type === 'radio') {
                if (control.checked) count += 1;
            } else if (String(control.value).trim() !== '') {
                count += 1;
            }
        }

        return count;
    }

    function clearUrl() {
        const url = new URL(form.action, window.location.origin);
        const order = form.elements.namedItem('orden')?.value;
        if (order && order !== 'novedades') url.searchParams.set('orden', order);
        return url;
    }

    function updateFilterChrome() {
        const count = selectedFilterCount();

        document.querySelectorAll('[data-catalog-clear]').forEach((link) => {
            link.href = clearUrl().toString();
            link.classList.toggle('invisible', count === 0 && link.closest('.catalog-filters__head'));
        });

        const mobileButton = document.querySelector('[data-bs-target="#catalogFilters"]');
        if (!mobileButton) return;

        let badge = mobileButton.querySelector('.catalog-filter-badge');
        if (count === 0) {
            badge?.remove();
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'catalog-filter-badge';
            mobileButton.append(badge);
        }
        badge.textContent = String(count);
    }

    function setLoading(loading) {
        page.classList.toggle('is-loading', loading);
        results()?.setAttribute('aria-busy', String(loading));

        const live = status();
        if (!live) return;

        if (loading) {
            live.textContent = 'Actualizando catálogo…';
        } else if (live.textContent === 'Actualizando catálogo…') {
            live.textContent = '';
        }
    }

    function validatePrices() {
        const minimum = form.elements.namedItem('precio_min');
        const maximum = form.elements.namedItem('precio_max');
        if (!minimum || !maximum) return true;

        maximum.setCustomValidity('');
        const min = minimum.value === '' ? null : Number(minimum.value);
        const max = maximum.value === '' ? null : Number(maximum.value);

        if (min !== null && max !== null && Number.isFinite(min) && Number.isFinite(max) && max < min) {
            maximum.setCustomValidity('El precio máximo debe ser igual o superior al mínimo.');
            maximum.reportValidity();
            return false;
        }

        return true;
    }

    function replaceFragment(documentNode, selector) {
        const current = document.querySelector(selector);
        const next = documentNode.querySelector(selector);
        if (current && next) current.replaceWith(next);
    }

    async function refreshCatalog(url, { historyMode = 'push', scroll = false } = {}) {
        if (!validatePrices()) return;

        requestController?.abort();
        const controller = new AbortController();
        requestController = controller;
        setLoading(true);

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const html = await response.text();
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');

            if (!nextDocument.querySelector('[data-catalog-results]')) {
                throw new Error('La respuesta no contiene el catálogo esperado.');
            }

            replaceFragment(nextDocument, '[data-catalog-team-context]');
            replaceFragment(nextDocument, '[data-catalog-active-filters]');
            replaceFragment(nextDocument, '[data-catalog-results]');

            document.title = nextDocument.title || document.title;

            const finalUrl = new URL(response.url, window.location.origin);
            if (historyMode === 'push' && finalUrl.href !== window.location.href) {
                history.pushState({}, '', finalUrl);
            } else if (historyMode === 'replace') {
                history.replaceState({}, '', finalUrl);
            }

            updateFilterChrome();
            document.dispatchEvent(new CustomEvent('catalog:updated'));

            const count = Number(results()?.dataset.resultCount ?? 0);
            const live = status();
            if (live) live.textContent = `${count} ${count === 1 ? 'camiseta encontrada' : 'camisetas encontradas'}.`;

            if (scroll) {
                document.querySelector('.catalog-main')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (error) {
            if (error.name === 'AbortError') return;

            const live = status();
            if (live) live.textContent = 'No se pudieron actualizar los resultados. Inténtalo de nuevo.';
        } finally {
            if (requestController === controller) {
                setLoading(false);
            }
        }
    }

    function scheduleRefresh(historyMode = 'replace') {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            refreshCatalog(formUrl(), { historyMode });
        }, 320);
    }

    function syncFormFromUrl(url) {
        const params = new URL(url, window.location.origin).searchParams;

        for (const control of form.elements) {
            if (!control.name) continue;

            let values = params.getAll(control.name);
            if (values.length === 0 && control.name.endsWith('[]')) {
                values = params.getAll(control.name.slice(0, -2));
            }
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = values.includes(control.value);
            } else if (control.name === 'orden') {
                control.value = params.get(control.name) ?? 'novedades';
            } else {
                control.value = params.get(control.name) ?? '';
            }
        }

        updateFilterChrome();
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        refreshCatalog(formUrl(), { historyMode: 'push' });
    });

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-catalog-debounce]')) return;
        refreshCatalog(formUrl(), { historyMode: 'push' });
    });

    form.addEventListener('input', (event) => {
        if (!event.target.matches('[data-catalog-debounce]')) return;
        scheduleRefresh('replace');
    });

    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-catalog-sort]')) return;
        refreshCatalog(formUrl(), { historyMode: 'push' });
    });

    document.addEventListener('click', (event) => {
        const paginationLink = event.target.closest('[data-catalog-pagination] a');
        if (paginationLink) {
            event.preventDefault();
            refreshCatalog(new URL(paginationLink.href), { historyMode: 'push', scroll: true });
            return;
        }

        const remove = event.target.closest('[data-filter-remove]');
        if (remove) {
            event.preventDefault();
            const { filterName: name, filterValue: value } = remove.dataset;

            for (const control of form.elements) {
                if (control.name !== name) continue;

                if (control.type === 'checkbox' || control.type === 'radio') {
                    if (control.value === value) control.checked = false;
                } else {
                    control.value = '';
                }
            }

            refreshCatalog(formUrl(), { historyMode: 'push' });
            updateFilterChrome();
            return;
        }

        const clear = event.target.closest('[data-catalog-clear]');
        if (clear) {
            event.preventDefault();
            const url = new URL(clear.href, window.location.origin);
            syncFormFromUrl(url);
            refreshCatalog(url, { historyMode: 'push' });
        }
    });

    const countrySearch = document.querySelector('[data-country-filter-search]');
    countrySearch?.addEventListener('input', () => {
        const query = normaliseText(countrySearch.value);
        let visible = 0;

        document.querySelectorAll('[data-country-option]').forEach((option) => {
            const matches = normaliseText(option.dataset.countryLabel).includes(query);
            option.classList.toggle('d-none', !matches);
            if (matches) visible += 1;
        });

        document.querySelector('[data-country-filter-empty]')?.classList.toggle('d-none', visible !== 0);
    });

    window.addEventListener('popstate', () => {
        syncFormFromUrl(window.location.href);
        refreshCatalog(new URL(window.location.href), { historyMode: 'none' });
    });

    updateFilterChrome();
}
