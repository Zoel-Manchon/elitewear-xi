<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductEquipmentController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StockAlertController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tienda
|--------------------------------------------------------------------------
*/

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/camisetas', [CatalogController::class, 'index'])->name('products.index');
Route::get('/camisetas/{product}', [CatalogController::class, 'show'])->name('products.show');
Route::post('/camisetas/{product}/resenas', [ReviewController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])->name('reviews.store');
Route::delete('/resenas/{review}', [ReviewController::class, 'destroy'])
    ->middleware('auth')->name('reviews.destroy');

// Buscador en tiempo real: se dispara con cada tecla, así que va limitado.
Route::get('/buscar', SearchController::class)->middleware('throttle:120,1')->name('search');

/*
|--------------------------------------------------------------------------
| Carrito
|--------------------------------------------------------------------------
| Sin el throttle, un POST en bucle a /carrito/items llena la base de datos.
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
    Route::get('/carrito/json', [CartController::class, 'show'])->name('cart.show');
    Route::post('/carrito/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('/carrito/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/carrito/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');

    // Probar códigos a ciegas es fuerza bruta: va limitado aparte.
    Route::post('/carrito/cupon', [CouponController::class, 'store'])
        ->middleware('throttle:8,1')->name('coupons.store');
    Route::delete('/carrito/cupon', [CouponController::class, 'destroy'])->name('coupons.destroy');
});

/*
|--------------------------------------------------------------------------
| Avisos de reposición
|--------------------------------------------------------------------------
*/

Route::post('/avisos', [StockAlertController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('stock-alerts.store');

Route::get('/avisos/{alert}/baja', [StockAlertController::class, 'destroy'])
    ->name('stock-alerts.destroy');

Auth::routes();

/*
|--------------------------------------------------------------------------
| Checkout de invitado, seguimiento y cuenta
|--------------------------------------------------------------------------
*/

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('checkout.store');
Route::get('/checkout/{order}/pago', [CheckoutController::class, 'payment'])->name('checkout.payment');
Route::get('/checkout/{order}/gracias', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/pagos/{order}/paypal', [PaymentController::class, 'create'])
    ->middleware('throttle:20,1')->name('payments.create');
Route::post('/pagos/{order}/paypal/capturar', [PaymentController::class, 'capture'])
    ->middleware('throttle:20,1')->name('payments.capture');

Route::get('/seguimiento', [TrackingController::class, 'index'])->name('tracking.index');
Route::post('/seguimiento', [TrackingController::class, 'lookup'])
    ->middleware('throttle:8,1')->name('tracking.lookup');
Route::get('/seguimiento/{order}', [TrackingController::class, 'show'])->name('tracking.show');
Route::get('/seguimiento/{order}/acceso', [TrackingController::class, 'email'])
    ->middleware('signed')->name('tracking.email');

Route::get('/favoritos', [WishlistController::class, 'index'])->name('wishlist.index');
Route::get('/favoritos/productos', [WishlistController::class, 'products'])
    ->middleware('throttle:60,1')->name('wishlist.products');

Route::middleware('auth')->group(function () {
    Route::post('/favoritos/sincronizar', [WishlistController::class, 'sync'])
        ->middleware('throttle:30,1')->name('wishlist.sync');
    Route::post('/favoritos/{product:id}', [WishlistController::class, 'store'])
        ->middleware('throttle:60,1')->name('wishlist.store');
    Route::delete('/favoritos/{product:id}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/mi-cuenta/pedidos', [OrderController::class, 'index'])->name('account.orders');
    Route::get('/mi-cuenta/pedidos/{order}', [OrderController::class, 'show'])->name('account.orders.show');
});

// El navegador envía aquí lo que la CSP bloquea. Sin sesión y limitado:
// es un endpoint público que cualquiera puede intentar inundar.
Route::post('/csp-report', CspReportController::class)
    ->middleware('throttle:30,1')->name('csp.report');

// Sin sesión ni CSRF: lo autentica la firma de PayPal, no una cookie.
Route::post('/webhooks/paypal', [PaymentController::class, 'webhook'])
    ->middleware('throttle:120,1')->name('webhooks.paypal');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
// No debe existir public/robots.txt: el servidor web lo serviría antes de Laravel
// y perderíamos la URL absoluta y dinámica del sitemap.
Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /checkout\nSitemap: ".route('sitemap')."\n")
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
| Doble candado: 'auth' y 'admin'. Nunca confíes en ocultar el enlace.
*/

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/productos', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/productos/nuevo', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/productos', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/productos/{product}/editar', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/productos/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('/productos/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
        Route::delete('/productos/{product}/imagenes/{image}', [AdminProductController::class, 'destroyImage'])
            ->name('products.images.destroy');
        Route::post('/productos/{product}/sportsdb/sincronizar', [ProductEquipmentController::class, 'sync'])
            ->middleware('throttle:20,1')
            ->name('products.sportsdb.sync');
        Route::post('/productos/{product}/sportsdb/equipaciones/{equipment}', [ProductEquipmentController::class, 'use'])
            ->middleware('throttle:20,1')
            ->name('products.sportsdb.use');

        Route::get('/pedidos', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/pedidos/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('/pedidos/{order}/estado', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

        Route::get('/codigos', [AdminCouponController::class, 'index'])->name('coupons.index');
        Route::get('/codigos/nuevo', [AdminCouponController::class, 'create'])->name('coupons.create');
        Route::post('/codigos', [AdminCouponController::class, 'store'])->name('coupons.store');
        Route::get('/codigos/{coupon}/editar', [AdminCouponController::class, 'edit'])->name('coupons.edit');
        Route::put('/codigos/{coupon}', [AdminCouponController::class, 'update'])->name('coupons.update');
        Route::delete('/codigos/{coupon}', [AdminCouponController::class, 'destroy'])->name('coupons.destroy');

        Route::get('/auditoria', [AuditLogController::class, 'index'])->name('audit.index');
    });
