<?php

namespace App\Http\Resources;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Order\PlaceOrder;
use App\Models\CartItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forma del carrito que viaja al navegador. Los importes van YA calculados
 * y formateados en servidor: el front no hace aritmética de dinero.
 */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal = $this->subtotal();
        $remaining = max(0, PlaceOrder::FREE_SHIPPING_FROM_CENTS - $subtotal->cents);

        return [
            'count' => $this->itemCount(),
            'subtotal' => $subtotal->format(),
            'subtotal_cents' => $subtotal->cents,
            'free_shipping_from' => Money::fromCents(PlaceOrder::FREE_SHIPPING_FROM_CENTS)->format(),
            'remaining_for_free_shipping' => Money::fromCents($remaining)->format(),
            'items' => $this->items->map(fn (CartItem $item) => [
                'id' => $item->id,
                'name' => $item->variant->product->name,
                'team' => $item->variant->product->team->name,
                'season' => $item->variant->product->season,
                'size' => $item->variant->size->value,
                'quantity' => $item->quantity,
                // El tope lo decide el servidor y viaja al cliente solo para
                // desactivar el botón "+". La validación real está en la API.
                'max_quantity' => min($item->variant->stock, AddItemToCart::MAX_PER_LINE),
                'unit_price' => $item->variant->price()->format(),
                'line_total' => $item->lineTotal()->format(),
                'image' => $item->variant->product->primaryImage?->url()
                    ?? asset('images/placeholder.svg'),
                'url' => route('products.show', $item->variant->product),
            ])->values(),
        ];
    }
}
