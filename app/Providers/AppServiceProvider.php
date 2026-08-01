<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\ProductVariant;
use App\Observers\ProductVariantObserver;
use App\Services\PayPal\PayPalGateway;
use App\Support\CartResolver;
use App\View\Composers\CartComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una sola instancia por petición: evita repetir la consulta del carrito.
        $this->app->scoped(CartResolver::class);

        // Enlazamos la interfaz, no la implementación: los tests
        // sustituyen PayPal por un doble sin tocar los controladores.
        $this->app->singleton(PaymentGateway::class, fn () => new PayPalGateway(
            clientId: (string) config('services.paypal.client_id'),
            secret: (string) config('services.paypal.secret'),
            baseUrl: (string) config('services.paypal.base_url'),
            webhookId: config('services.paypal.webhook_id'),
        ));
    }

    public function boot(): void
    {
        // Sin esto la paginación se renderiza con markup de Tailwind.
        Paginator::useBootstrapFive();

        // Avisa a quien se apuntó cuando una talla vuelve a tener stock.
        ProductVariant::observe(ProductVariantObserver::class);

        // Convierte cualquier N+1 en una excepción ruidosa fuera de producción.
        Model::preventLazyLoading(! app()->isProduction());

        // Comparte $cart y $cartCount con el layout y el desplegable.
        View::composer(['layouts.app', 'partials.cart-drawer'], CartComposer::class);
    }
}
