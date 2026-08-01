/**
 * Aparición progresiva de las rejillas al entrar en pantalla.
 * Respeta prefers-reduced-motion vía CSS, y se desconecta tras revelar
 * cada elemento para no dejar observadores vivos.
 */

const targets = document.querySelectorAll('.row > [class*="col-"] > .shirt-card');

if (targets.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry, index) => {
                if (!entry.isIntersecting) return;

                // Escalonado corto: 40 ms entre tarjetas, tope de 240 ms.
                setTimeout(() => entry.target.classList.add('is-in'), Math.min(index * 40, 240));
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -8% 0px' },
    );

    targets.forEach((node) => {
        node.classList.add('reveal');
        observer.observe(node);
    });
}
