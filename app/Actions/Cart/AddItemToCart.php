<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddItemToCart
{
    /** Tope por línea: evita que alguien "reserve" todo el stock. */
    public const MAX_PER_LINE = 10;

    public function handle(Cart $cart, ProductVariant $variant, int $quantity): Cart
    {
        return DB::transaction(function () use ($cart, $variant, $quantity): Cart {
            /** @var CartItem|null $item */
            $item = $cart->items()
                ->where('product_variant_id', $variant->id)
                ->first();

            $currentQuantity = $item instanceof CartItem ? $item->quantity : 0;
            $total = $currentQuantity + $quantity;

            // Comprobación blanda: el stock definitivo se bloquea al confirmar
            // el pedido. Aquí solo evitamos que el carrito mienta al usuario.
            if ($total > $variant->stock) {
                throw ValidationException::withMessages([
                    'quantity' => "Solo quedan {$variant->stock} unidades de la talla {$variant->size->value}.",
                ]);
            }

            if ($total > self::MAX_PER_LINE) {
                throw ValidationException::withMessages([
                    'quantity' => 'Máximo '.self::MAX_PER_LINE.' unidades por talla.',
                ]);
            }

            if ($item instanceof CartItem) {
                $item->update(['quantity' => $total]);
            } else {
                $cart->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                ]);
            }

            $cart->touch();

            return $cart->load([
                'items.variant.product.team',
                'items.variant.product.primaryImage',
            ]);
        });
    }
}
