import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

/**
 * Galería de producto.
 *
 * Tres capas, de menos a más compromiso del usuario:
 *   1. miniaturas que cambian la imagen principal
 *   2. lupa al pasar el cursor — en una camiseta retro lo que decide la
 *      compra son detalles pequeños: escudo, tejido, etiqueta interior
 *   3. clic abre PhotoSwipe: pantalla completa, zoom con rueda o pellizco,
 *      arrastre en móvil y navegación con teclado
 *
 * Todo en un fichero, sin JS inline, para poder servir una CSP estricta.
 */

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const stage = gallery.querySelector('[data-gallery-stage]');
    const main = gallery.querySelector('[data-gallery-main]');
    const lens = gallery.querySelector('[data-gallery-lens]');
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];

    if (!stage || !main) return;

    let index = 0;

    const show = (position) => {
        const thumb = thumbs[position];
        if (!thumb) return;

        index = position;
        main.src = thumb.dataset.full;
        main.alt = thumb.dataset.alt ?? '';

        if (lens) lens.style.backgroundImage = `url("${thumb.dataset.full}")`;

        thumbs.forEach((node, i) =>
            node.setAttribute('aria-current', i === position ? 'true' : 'false'),
        );
    };

    thumbs.forEach((thumb, position) => {
        thumb.addEventListener('click', () => show(position));
    });

    if (thumbs.length) show(0);

    // --- Lupa ---------------------------------------------------------------

    if (lens && window.matchMedia('(hover: hover)').matches) {
        stage.addEventListener('mousemove', (event) => {
            const rect = stage.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) * 100;
            const y = ((event.clientY - rect.top) / rect.height) * 100;
            lens.style.backgroundPosition = `${x}% ${y}%`;
        });
    }

    // --- Pantalla completa --------------------------------------------------

    const lightbox = new PhotoSwipeLightbox({
        gallery,
        children: '[data-gallery-thumb]',
        pswpModule: () => import('photoswipe'),
        bgOpacity: 0.96,
        padding: { top: 24, bottom: 24, left: 16, right: 16 },
    });

    lightbox.addFilter('itemData', (item, i) => {
        const thumb = thumbs[i];

        return {
            src: thumb.dataset.full,
            width: Number(thumb.dataset.width ?? 1400),
            height: Number(thumb.dataset.height ?? 1750),
            alt: thumb.dataset.alt ?? '',
        };
    });

    lightbox.init();

    stage.addEventListener('click', () => lightbox.loadAndOpen(index));

    stage.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            lightbox.loadAndOpen(index);
        }
    });
});
