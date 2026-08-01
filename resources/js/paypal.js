import { request } from './http';

/**
 * Botones de PayPal.
 *
 * El navegador no ve el importe en ningún momento: pide al servidor que
 * cree la orden (el servidor la calcula desde el pedido) y luego le pide
 * que la capture. Todo lo que puede manipular el cliente es el id de la
 * orden, que el servidor contrasta contra el pedido antes de cobrar.
 */

const mount = document.getElementById('paypal-buttons');
const errorBox = document.getElementById('payment-error');

const fail = (message) => {
    if (!errorBox) return;
    errorBox.textContent = message;
    errorBox.classList.remove('d-none');
};

if (mount) {
    const start = () => {
        if (!window.paypal) {
            fail('No se ha podido cargar PayPal. Recarga la página o inténtalo más tarde.');
            return;
        }

        window.paypal
            .Buttons({
                style: { layout: 'vertical', shape: 'rect', label: 'pay', height: 48 },

                createOrder: async () => {
                    const payload = await request(mount.dataset.create, { method: 'POST' });
                    return payload.id;
                },

                onApprove: async (data) => {
                    const payload = await request(mount.dataset.capture, {
                        method: 'POST',
                        body: { provider_order_id: data.orderID },
                    });

                    if (payload.redirect) {
                        window.location.href = payload.redirect;
                        return;
                    }

                    fail('Hemos recibido el pago pero necesitamos revisarlo. Te escribiremos en breve.');
                },

                onError: () => fail('El pago no se ha podido completar. No se ha cobrado nada.'),
            })
            .render('#paypal-buttons');
    };

    // El SDK se carga con defer: puede estar listo o no cuando llega este módulo.
    if (window.paypal) {
        start();
    } else {
        window.addEventListener('load', start, { once: true });
    }
}
