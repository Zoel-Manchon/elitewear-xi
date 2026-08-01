import { request, escapeHtml } from './http';
import { toast } from './toast';

/**
 * Cajón del carrito.
 *
 * El navegador nunca calcula precios: envía la intención (variante +
 * cantidad) y el servidor devuelve el carrito ya calculado y formateado.
 * Si alguien manipula el DOM, lo único que consigue es que su propia
 * pantalla mienta.
 */

const FREE_SHIPPING_CENTS = 10000;

const el = {
    drawer: () => document.getElementById('cartDrawer'),
    body: () => document.querySelector('[data-cart-body]'),
    footer: () => document.querySelector('[data-cart-footer]'),
    count: () => document.querySelector('[data-cart-count]'),
};

let state = null;

function openDrawer() {
    const node = el.drawer();
    if (node && window.bootstrap) {
        window.bootstrap.Offcanvas.getOrCreateInstance(node).show();
    }
}

function announce(message, type = 'error') {
    toast(message, { type });
}

function shippingMeter(cart) {
    const remaining = FREE_SHIPPING_CENTS - cart.subtotal_cents;
    const pct = Math.min(100, (cart.subtotal_cents / FREE_SHIPPING_CENTS) * 100);

    if (remaining <= 0) {
        return `
        <div class="shipping-meter shipping-meter--done">
            <p class="small mb-2 mt-0">Envío gratis incluido.</p>
            <div class="shipping-meter__track">
                <div class="shipping-meter__fill" style="width:100%"></div>
            </div>
        </div>`;
    }

    return `
    <div class="shipping-meter">
        <p class="small mb-2 mt-0">
            Te faltan <strong class="data">${cart.remaining_for_free_shipping}</strong>
            para el envío gratis.
        </p>
        <div class="shipping-meter__track">
            <div class="shipping-meter__fill" style="width:${pct}%"></div>
        </div>
    </div>`;
}

function lineTemplate(item) {
    return `
    <div class="cart-line" data-line="${item.id}">
        <img src="${item.image}" alt="" loading="lazy">

        <div>
            <div class="shirt-card__team">${escapeHtml(item.team)} · ${escapeHtml(item.season ?? '')}</div>
            <a href="${item.url}" class="d-block fw-semibold mb-1">${escapeHtml(item.name)}</a>
            <div class="d-flex align-items-center gap-2">
                <span class="data small text-body-secondary">Talla ${escapeHtml(item.size)}</span>
                <span class="qty-stepper">
                    <button type="button" data-qty="${item.id}" data-delta="-1"
                            aria-label="Quitar una unidad">−</button>
                    <output>${item.quantity}</output>
                    <button type="button" data-qty="${item.id}" data-delta="1"
                            ${item.quantity >= item.max_quantity ? 'disabled' : ''}
                            aria-label="Añadir una unidad">+</button>
                </span>
            </div>
        </div>

        <div class="text-end">
            <div class="price">${item.line_total}</div>
            <button type="button" class="btn btn-link btn-sm p-0 text-body-secondary"
                    data-remove="${item.id}">Quitar</button>
        </div>
    </div>`;
}

function render(cart) {
    state = cart;

    const count = el.count();
    if (count) {
        count.textContent = cart.count;
        count.classList.toggle('d-none', cart.count === 0);
    }

    const body = el.body();
    const footer = el.footer();
    if (!body) return;

    if (cart.count === 0) {
        body.innerHTML = `
        <div class="cart-empty">
            <div class="kit-number">0</div>
            <p class="text-body-secondary mb-3">Todavía no has elegido ninguna camiseta.</p>
            <a href="/camisetas" class="btn btn-primary">Ver el catálogo</a>
        </div>`;
        if (footer) footer.innerHTML = '';
        return;
    }

    body.innerHTML = cart.items.map(lineTemplate).join('');

    if (footer) {
        footer.innerHTML = `
        ${shippingMeter(cart)}
        <div class="d-flex justify-content-between align-items-baseline py-2 border-top">
            <span class="eyebrow">Subtotal</span>
            <span class="price fs-4">${cart.subtotal}</span>
        </div>
        <div class="d-grid gap-2">
            <a href="/checkout" class="btn btn-primary btn-lg">Finalizar compra</a>
            <a href="/carrito" class="btn btn-outline-light btn-sm">Ver el carrito completo</a>
        </div>`;
    }
}

async function mutate(promise) {
    const body = el.body();
    body?.classList.add('opacity-50');

    try {
        render(await promise);
    } catch (error) {
        announce(error.message);
    } finally {
        body?.classList.remove('opacity-50');
    }
}

// --- Añadir desde la ficha de producto --------------------------------------

document.querySelectorAll('form[data-add-to-cart]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const data = new FormData(form);
        const button = form.querySelector('button[type="submit"]');
        const original = button?.textContent;

        if (!data.get('product_variant_id')) {
            announce('Elige una talla antes de añadir al carrito.');
            return;
        }

        button?.setAttribute('disabled', 'disabled');
        if (button) button.textContent = 'Añadiendo…';

        try {
            render(
                await request(form.action, {
                    method: 'POST',
                    body: {
                        product_variant_id: data.get('product_variant_id'),
                        quantity: Number(data.get('quantity') ?? 1),
                    },
                }),
            );
            openDrawer();
        } catch (error) {
            openDrawer();
            announce(error.message);
        } finally {
            button?.removeAttribute('disabled');
            if (button && original) button.textContent = original;
        }
    });
});

// --- Cantidad y borrado dentro del cajón ------------------------------------

document.addEventListener('click', (event) => {
    const step = event.target.closest('[data-qty]');
    if (step) {
        const item = state?.items.find((i) => String(i.id) === step.dataset.qty);
        if (!item) return;

        const quantity = item.quantity + Number(step.dataset.delta);

        mutate(
            request(`/carrito/items/${item.id}`, {
                method: 'PATCH',
                body: { quantity: Math.max(0, quantity) },
            }),
        );
        return;
    }

    const remove = event.target.closest('[data-remove]');
    if (remove) {
        mutate(request(`/carrito/items/${remove.dataset.remove}`, { method: 'DELETE' }));
    }
});

// --- Estado inicial ---------------------------------------------------------

if (el.drawer()) {
    request('/carrito/json').then(render).catch(() => {});
}
