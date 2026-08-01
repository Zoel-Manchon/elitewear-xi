<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Al hacer login, el carrito de invitado se funde con el del usuario.
 * Sin esto, meter cosas al carrito y luego iniciar sesión las pierde:
 * uno de los abandonos de compra más tontos que existen.
 */
class MergeGuestCart
{
    public function handle(Cart $guestCart, int $userId): Cart
    {
        return DB::transaction(function () use ($guestCart, $userId): Cart {
            $userCart = Cart::query()->firstOrCreate(['user_id' => $userId]);

            if ($guestCart->is($userCart)) {
                return $userCart;
            }

            $guestCart->loadMissing('items.variant');

            $existing = $userCart->items()
                ->pluck('quantity', 'product_variant_id');

            /** @var Collection<int, CartItem> $guestItems */
            $guestItems = $guestCart->items;

            foreach ($guestItems as $item) {
                $current = (int) $existing->get($item->product_variant_id, 0);

                $quantity = min(
                    $current + $item->quantity,
                    AddItemToCart::MAX_PER_LINE,
                    max($item->variant->stock, 1),
                );

                $userCart->items()->updateOrCreate(
                    ['product_variant_id' => $item->product_variant_id],
                    ['quantity' => $quantity],
                );
            }

            // La FK con cascade elimina también sus líneas.
            $guestCart->delete();

            return $userCart->load([
                'items.variant.product.team',
                'items.variant.product.primaryImage',
            ]);
        });
    }
}
