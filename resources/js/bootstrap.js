/**
 * Bootstrap 5.
 *
 * Se expone en window porque cart.js necesita abrir el cajón lateral
 * mediante la API de Offcanvas (`bootstrap.Offcanvas.getOrCreateInstance`).
 * Sin esta línea, importar el paquete registra los data-attributes pero no
 * deja acceso programático a los componentes.
 *
 * Nota: axios ya no se usa. El proyecto habla con el servidor mediante
 * `fetch` desde resources/js/http.js, que además centraliza el token CSRF
 * y el manejo de respuestas que no son JSON.
 */
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;
