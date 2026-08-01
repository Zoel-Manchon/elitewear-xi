/**
 * Avisos flotantes.
 *
 * Antes los mensajes del carrito se inyectaban dentro del propio cajón, lo
 * que obligaba a tenerlo abierto para enterarte de un error. Un toast es
 * visible desde cualquier parte de la página y no desplaza el contenido.
 */

let stack;

function container() {
    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'toast-stack';
        // Los lectores de pantalla lo anuncian sin robar el foco.
        stack.setAttribute('role', 'status');
        stack.setAttribute('aria-live', 'polite');
        document.body.append(stack);
    }

    return stack;
}

export function toast(message, { type = 'success', timeout = 4200 } = {}) {
    const note = document.createElement('div');
    note.className = `toast-note ${type === 'error' ? 'toast-note--error' : ''}`;
    note.textContent = message;

    container().append(note);

    setTimeout(() => {
        note.classList.add('is-leaving');
        note.addEventListener('animationend', () => note.remove(), { once: true });
    }, timeout);
}
