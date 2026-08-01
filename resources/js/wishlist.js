import { escapeHtml, request } from './http';
import { toast } from './toast';

const STORAGE_KEY = 'retro-shirts-wishlist-v1';
const authenticated = document.body.dataset.authenticated === 'true';
let ids = [];

function localIds() {
    try {
        const value = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
        return Array.isArray(value)
            ? [...new Set(value.map(Number).filter(Number.isInteger).filter((id) => id > 0))].slice(0, 50)
            : [];
    } catch {
        return [];
    }
}

function saveLocal(next) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
}

function updateUi() {
    const selected = new Set(ids.map(Number));

    document.querySelectorAll('[data-wishlist-toggle]').forEach((button) => {
        const active = selected.has(Number(button.dataset.productId));
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
        button.setAttribute(
            'aria-label',
            active ? 'Quitar de favoritos' : 'Guardar en favoritos',
        );
    });

    document.querySelectorAll('[data-wishlist-count]').forEach((badge) => {
        badge.textContent = ids.length;
        badge.classList.toggle('d-none', ids.length === 0);
    });

    const pageCount = document.querySelector('[data-wishlist-page-count]');
    if (pageCount) pageCount.textContent = `${ids.length} ${ids.length === 1 ? 'camiseta' : 'camisetas'}`;

    const empty = document.querySelector('[data-wishlist-empty]');
    if (empty) empty.classList.toggle('d-none', ids.length > 0);
}

function guestCard(product) {
    const stock = product.stock === 0
        ? '<span class="shirt-card__flag shirt-card__flag--out">Agotada</span>'
        : product.stock <= 5
            ? `<span class="shirt-card__flag shirt-card__flag--low">Últimas ${product.stock}</span>`
            : '';

    return `
    <div class="col-6 col-lg-3" data-wishlist-grid-item="${product.id}">
        <article class="shirt-card h-100 d-flex flex-column" data-product-card="${product.id}">
            <div class="shirt-card__media">
                <a href="${product.url}" class="shirt-card__media-link" aria-label="Ver ${escapeHtml(product.name)}">
                    <img src="${product.image}" alt="${escapeHtml(product.name)}" loading="lazy">
                    ${stock}
                </a>
                <button type="button" class="wishlist-toggle is-active" data-wishlist-toggle
                        data-product-id="${product.id}" aria-pressed="true" aria-label="Quitar de favoritos">
                    <svg width="20" height="20" aria-hidden="true"><use href="#icon-heart"/></svg>
                </button>
            </div>
            <div class="shirt-card__body mt-auto">
                <div class="shirt-card__team">${escapeHtml(product.team)}</div>
                <h3 class="shirt-card__name"><a href="${product.url}">${escapeHtml(product.season ?? '')} · ${escapeHtml(product.name)}</a></h3>
                <div class="shirt-card__foot">
                    <div class="price">${escapeHtml(product.price)}</div>
                    <a class="shirt-card__cta" href="${product.url}">Ver ficha</a>
                </div>
            </div>
        </article>
    </div>`;
}

async function renderGuestPage() {
    const grid = document.querySelector('[data-wishlist-grid]');
    if (!grid || authenticated) return;

    if (ids.length === 0) {
        grid.innerHTML = '';
        return;
    }

    const query = new URLSearchParams();
    ids.forEach((id) => query.append('ids[]', String(id)));

    try {
        const products = await request(`/favoritos/productos?${query}`);
        const available = products.map((product) => Number(product.id));
        ids = ids.filter((id) => available.includes(id));
        saveLocal(ids);
        grid.innerHTML = products.map(guestCard).join('');
        updateUi();
    } catch (error) {
        toast(error.message, { type: 'error' });
    }
}

async function bootstrapWishlist() {
    const local = localIds();

    if (!authenticated) {
        ids = local;
        updateUi();
        await renderGuestPage();
        return;
    }

    try {
        const state = await request('/favoritos/sincronizar', {
            method: 'POST',
            body: { product_ids: local },
        });
        ids = state.ids.map(Number);
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        ids = local;
    }

    updateUi();
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-wishlist-toggle]');
    if (!button) return;

    event.preventDefault();
    const productId = Number(button.dataset.productId);
    const active = ids.includes(productId);
    button.disabled = true;

    try {
        if (authenticated) {
            const state = await request(`/favoritos/${productId}`, {
                method: active ? 'DELETE' : 'POST',
            });
            ids = state.ids.map(Number);
            toast(state.message ?? (active ? 'Eliminada de favoritos.' : 'Guardada en favoritos.'));
        } else {
            ids = active ? ids.filter((id) => id !== productId) : [...ids, productId].slice(0, 50);
            saveLocal(ids);
            toast(active ? 'Eliminada de favoritos.' : 'Guardada en este navegador.');
        }

        const pageItem = document.querySelector(`[data-wishlist-grid-item="${productId}"]`);
        if (active && pageItem) pageItem.remove();
        updateUi();
    } catch (error) {
        toast(error.message, { type: 'error' });
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('catalog:updated', updateUi);

bootstrapWishlist();
