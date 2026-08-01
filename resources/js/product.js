import { request } from './http';
import { toast } from './toast';

/**
 * Comportamientos de la ficha de producto: aviso de reposición y barra
 * fija de compra en móvil.
 */

// --- Aviso de reposición ----------------------------------------------------

document.querySelectorAll('form[data-stock-alert]').forEach((form) => {
    const feedback = form.querySelector('[data-stock-alert-feedback]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const data = new FormData(form);
        const button = form.querySelector('button');

        button?.setAttribute('disabled', 'disabled');

        try {
            const payload = await request(form.action, {
                method: 'POST',
                body: {
                    product_variant_id: data.get('product_variant_id'),
                    email: data.get('email'),
                },
            });

            toast(payload.message);
            if (feedback) feedback.innerHTML = '';

            form.querySelector('input[type="email"]').value = '';
        } catch (error) {
            toast(error.message, { type: 'error' });
        } finally {
            button?.removeAttribute('disabled');
        }
    });
});

// --- Barra fija de compra ---------------------------------------------------

const bar = document.querySelector('[data-buy-bar]');
const addForm = document.querySelector('form[data-add-to-cart]');

if (bar && addForm) {
    // Aparece solo cuando el formulario de compra ha salido de pantalla:
    // mientras se ve el original, una segunda barra sería ruido.
    const observer = new IntersectionObserver(
        ([entry]) => bar.classList.toggle('is-visible', !entry.isIntersecting),
        { rootMargin: '-70px 0px 0px 0px' },
    );

    observer.observe(addForm);

    bar.querySelector('[data-buy-bar-action]')?.addEventListener('click', () => {
        addForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        addForm.querySelector('input[name="product_variant_id"]:not(:disabled)')?.focus();
    });
}
