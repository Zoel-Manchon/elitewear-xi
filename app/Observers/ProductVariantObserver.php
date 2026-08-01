<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Notifications\BackInStock;
use Illuminate\Support\Facades\Notification;

class ProductVariantObserver
{
    /**
     * Dispara los avisos cuando una talla pasa de agotada a disponible.
     *
     * La condición mira el valor ANTERIOR, no solo el actual: sin eso,
     * cualquier reposición sobre stock existente reenviaría los avisos.
     */
    public function updated(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock')) {
            return;
        }

        $before = (int) $variant->getOriginal('stock');

        if ($before > 0 || $variant->stock < 1) {
            return;
        }

        StockAlert::pending()
            ->where('product_variant_id', $variant->id)
            ->with('variant.product')
            ->chunkById(100, function ($alerts) {
                foreach ($alerts as $alert) {
                    Notification::route('mail', $alert->email)
                        ->notify(new BackInStock($alert));

                    // Se marca en vez de borrarse: así el mismo correo no
                    // recibe dos avisos si el stock oscila, y queda rastro.
                    $alert->update(['notified_at' => now()]);
                }
            });
    }
}
