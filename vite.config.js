import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Fijamos el host para que la URL que Vite escribe en public/hot sea
        // predecible. Sin esto puede resolver a [::1] (IPv6) y, aunque el
        // middleware lo lee del propio fichero, tener un solo origen estable
        // simplifica depurar la CSP.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },
});
