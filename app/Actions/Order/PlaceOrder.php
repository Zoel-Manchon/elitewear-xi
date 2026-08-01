<?php

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\Orders\OrderNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Convierte un carrito en un pedido con stock y cupones bloqueados. */
class PlaceOrder
{
    public const SHIPPING_CENTS = 495;

    public const FREE_SHIPPING_FROM_CENTS = 10000;

    public function __construct(private readonly OrderNotifier $notifier) {}

    /**
     * @param  array<string, string|null>  $shippingAddress
     */
    public function handle(
        Cart $cart,
        array $shippingAddress,
        string $email,
        ?int $userId,
        ?Coupon $coupon = null,
    ): Order {
        abort_if($cart->items->isEmpty(), 422, 'El carrito está vacío.');

        $order = DB::transaction(function () use ($cart, $shippingAddress, $email, $userId, $coupon): Order {
            // Dos envíos simultáneos del mismo formulario no pueden generar
            // dos pedidos: bloqueamos el carrito y recargamos sus líneas
            // dentro de la transacción antes de tocar el stock.
            $lockedCart = Cart::query()
                ->whereKey($cart->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCart->load('items');

            /** @var Collection<int, CartItem> $lockedItems */
            $lockedItems = $lockedCart->items;

            abort_if($lockedItems->isEmpty(), 422, 'El carrito ya se ha procesado.');

            /** @var Collection<int, ProductVariant> $variants */
            $variants = ProductVariant::query()
                ->with('product.team')
                ->whereIn('id', $lockedItems->pluck('product_variant_id')->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;

            /** @var array<int, array<string, int|string|null>> $lines */
            $lines = [];

            foreach ($lockedItems as $item) {
                $variant = $variants->get($item->product_variant_id);

                if (! $variant instanceof ProductVariant || ! $variant->is_active) {
                    throw new InsufficientStockException('Un artículo de tu carrito', 0);
                }

                if ($variant->stock < $item->quantity) {
                    throw new InsufficientStockException(
                        $variant->product->name.' (talla '.$variant->size->value.')',
                        $variant->stock,
                    );
                }

                $unitPrice = $variant->product->base_price_cents + $variant->price_delta_cents;
                $lineTotal = $unitPrice * $item->quantity;
                $subtotal += $lineTotal;

                $lines[] = [
                    'product_variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $variant->product->name,
                    'team_name' => $variant->product->team->name,
                    'season' => $variant->product->season,
                    'size' => $variant->size->value,
                    'sku' => $variant->sku,
                    'unit_price_cents' => $unitPrice,
                    'quantity' => $item->quantity,
                    'line_total_cents' => $lineTotal,
                ];

                $variant->decrement('stock', $item->quantity);
            }

            $discount = 0;
            $lockedCoupon = null;

            if ($coupon !== null) {
                $lockedCoupon = Coupon::query()
                    ->whereKey($coupon->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedCoupon !== null && $lockedCoupon->rejectionReason($subtotal) === null) {
                    $discount = $lockedCoupon->discountFor($subtotal);
                    $lockedCoupon->increment('redemptions_count');
                } else {
                    $lockedCoupon = null;
                }
            }

            $payable = $subtotal - $discount;
            $shipping = $payable >= self::FREE_SHIPPING_FROM_CENTS ? 0 : self::SHIPPING_CENTS;

            $order = Order::query()->create([
                'user_id' => $userId,
                'number' => 'PENDIENTE',
                'status' => OrderStatus::Pending,
                'email' => mb_strtolower(trim($email)),
                'coupon_id' => $lockedCoupon?->id,
                'coupon_code' => $lockedCoupon?->code,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'shipping_cents' => $shipping,
                'total_cents' => $payable + $shipping,
                'currency' => 'EUR',
                'shipping_address' => $shippingAddress,
                'placed_at' => now(),
            ]);

            $order->update([
                'number' => sprintf('RS-%s-%06d', now()->year, $order->id),
            ]);

            $order->items()->createMany($lines);
            $lockedCart->items()->delete();

            return $order->load('items');
        });

        $this->notifier->placed($order);

        return $order;
    }
}
