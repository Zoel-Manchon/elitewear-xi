import noUiSlider from 'nouislider';
import 'nouislider/dist/nouislider.css';

let activeRequest = null;
let searchTimer = null;

/**
 * Construye la URL partiendo de la que ya hay en el navegador y
 * sobrescribiendo únicamente los campos del formulario que disparó el cambio.
 *
 * Antes se construía desde cero con el FormData de ese formulario, así que
 * cada uno borraba lo del otro: cambiar el orden perdía los filtros y filtrar
 * perdía el orden. El estado del catálogo vive en la URL, no en un formulario
 * concreto.
 */
const buildUrl = (form) => {
    const url = new URL(window.location.href);
    const params = url.searchParams;

    // Solo se limpian las claves que ESTE formulario controla.
    for (const element of form.elements) {
        if (element.name) params.delete(element.name);
    }

    for (const [key, rawValue] of new FormData(form).entries()) {
        const value = String(rawValue).trim();
        const control = form.elements.namedItem(key);
        const defaultFilter = control instanceof HTMLElement
            ? control.dataset.defaultFilter
            : undefined;

        if (value !== '' && value !== defaultFilter) params.append(key, value);
    }

    // Cualquier cambio de filtro u orden devuelve a la primera página:
    // seguir en la 7 tras filtrar suele dejar la rejilla vacía.
    params.delete('page');
    params.delete('parcial');

    url.search = params.toString();
    return url;
};

const setBusy = (root, busy) => {
    root.classList.toggle('is-filtering', busy);
    root.setAttribute('aria-busy', busy ? 'true' : 'false');

    const status = root.querySelector('[data-filter-status]');
    if (status) status.textContent = busy ? 'Actualizando resultados…' : '';
};

const loadCatalog = async (url, pushState = true) => {
    const currentRoot = document.querySelector('[data-live-catalog]');
    if (!currentRoot) {
        window.location.assign(url);
        return;
    }

    activeRequest?.abort();
    activeRequest = new AbortController();
    setBusy(currentRoot, true);

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html',
            },
            signal: activeRequest.signal,
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const html = await response.text();
        const documentFragment = new DOMParser().parseFromString(html, 'text/html');
        const nextRoot = documentFragment.querySelector('[data-live-catalog]');

        if (!nextRoot) throw new Error('La respuesta no contiene el catálogo.');

        currentRoot.replaceWith(nextRoot);
        if (pushState) window.history.pushState({}, '', url);
        initCatalogFilters(nextRoot);
        document.dispatchEvent(new CustomEvent('catalog:updated'));
        nextRoot.scrollIntoView({ block: 'start', behavior: 'smooth' });
    } catch (error) {
        if (error.name !== 'AbortError') {
            window.location.assign(url);
        }
    }
};

const initPriceSlider = (root, form) => {
    const node = root.querySelector('#priceSlider');
    if (!node || node.noUiSlider) return;

    const min = Number(node.dataset.min ?? 0);
    const max = Number(node.dataset.max ?? 20000);
    const from = Number(node.dataset.from ?? min);
    const to = Number(node.dataset.to ?? max);
    const inputMin = root.querySelector('#precio_min');
    const inputMax = root.querySelector('#precio_max');

    const euros = (cents) => new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    }).format(cents / 100);

    noUiSlider.create(node, {
        start: [from, to],
        connect: true,
        step: 500,
        range: { min, max },
        tooltips: [
            { to: euros, from: Number },
            { to: euros, from: Number },
        ],
    });

    node.noUiSlider.on('update', (values) => {
        if (inputMin) inputMin.value = (Number(values[0]) / 100).toFixed(2);
        if (inputMax) inputMax.value = (Number(values[1]) / 100).toFixed(2);
    });

    node.noUiSlider.on('change', () => loadCatalog(buildUrl(form)));

    [inputMin, inputMax].forEach((input, position) => {
        input?.addEventListener('change', () => {
            const values = [null, null];
            values[position] = Math.round(Number(input.value) * 100);
            node.noUiSlider.set(values);
            loadCatalog(buildUrl(form));
        });
    });
};

function initCatalogFilters(root = document.querySelector('[data-live-catalog]')) {
    if (!root || root.dataset.liveReady === 'true') return;
    root.dataset.liveReady = 'true';

    const filterForm = root.querySelector('[data-live-filters]');
    const sortForm = root.querySelector('[data-live-sort]');

    if (filterForm) {
        initPriceSlider(root, filterForm);

        // Prueba de vida: si este código no corre, el botón sigue visible y
        // el formulario se envía como GET normal. Nunca dejamos al usuario
        // sin manera de filtrar.
        filterForm.querySelector('[data-filter-submit]')?.classList.add('d-none');

        filterForm.querySelectorAll('input[type="radio"], select[data-live-control]').forEach((control) => {
            control.addEventListener('change', () => loadCatalog(buildUrl(filterForm)));
        });

        const search = filterForm.querySelector('[data-live-search]');
        search?.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => loadCatalog(buildUrl(filterForm)), 350);
        });

        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            loadCatalog(buildUrl(filterForm));
        });
    }

    sortForm?.querySelectorAll('[data-live-control]').forEach((control) => {
        control.addEventListener('change', () => loadCatalog(buildUrl(sortForm)));
    });

    sortForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        loadCatalog(buildUrl(sortForm));
    });

    root.addEventListener('click', (event) => {
        const link = event.target.closest('[data-live-link], .pagination a');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const url = new URL(link.href, window.location.origin);
        if (url.origin !== window.location.origin) return;

        event.preventDefault();
        loadCatalog(url);
    });
}

window.addEventListener('popstate', () => loadCatalog(window.location.href, false));
initCatalogFilters();
