<?php

namespace App\Http\Controllers;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Order\PlaceOrder;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\CartResolver;
use App\Support\CouponSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartResolver $resolver,
        private readonly CouponSession $coupons,
    ) {}

    public function index(): View
    {
        $cart = $this->resolver->current();
        $subtotal = $cart?->subtotal()->cents ?? 0;
        $coupon = $cart ? $this->coupons->validFor($subtotal) : null;
        $discount = $coupon?->discountFor($subtotal) ?? 0;
        $payable = max(0, $subtotal - $discount);
        $shipping = $cart
            ? ($payable >= PlaceOrder::FREE_SHIPPING_FROM_CENTS ? 0 : PlaceOrder::SHIPPING_CENTS)
            : 0;

        return view('shop.cart', [
            'cart' => $cart,
            'coupon' => $coupon,
            'discountCents' => $discount,
            'shippingCents' => $shipping,
            'totalCents' => $payable + $shipping,
            'freeShippingRemainingCents' => max(0, PlaceOrder::FREE_SHIPPING_FROM_CENTS - $payable),
        ]);
    }

    /** Devuelve el carrito en JSON: lo consume el desplegable del navbar. */
    public function show(): JsonResource|JsonResponse
    {
        $cart = $this->resolver->current();

        return $cart
            ? new CartResource($cart)
            : response()->json(['count' => 0, 'subtotal' => '0,00 €', 'items' => []]);
    }

    public function store(StoreCartItemRequest $request, AddItemToCart $action): JsonResource|RedirectResponse
    {
        $variant = ProductVariant::with('product')->findOrFail(
            $request->integer('product_variant_id')
        );

        $cart = $action->handle(
            $this->resolver->currentOrCreate(),
            $variant,
            $request->integer('quantity'),
        );

        return $this->respond($request, $cart, 'Añadido al carrito.');
    }

    public function update(UpdateCartItemRequest $request, CartItem $item): JsonResource|RedirectResponse
    {
        $this->authorizeItem($item);

        $quantity = $request->integer('quantity');

        if ($quantity === 0) {
            $item->delete();
        } else {
            if ($quantity > $item->variant->stock) {
                return back()->withErrors([
                    'quantity' => "Solo quedan {$item->variant->stock} unidades.",
                ]);
            }

            $item->update(['quantity' => $quantity]);
        }

        return $this->respond($request, $this->resolver->current()?->fresh(['items.variant.product.team', 'items.variant.product.primaryImage']), 'Carrito actualizado.');
    }

    public function destroy(Request $request, CartItem $item): JsonResource|RedirectResponse
    {
        $this->authorizeItem($item);
        $item->delete();

        return $this->respond($request, $this->resolver->current()?->fresh(['items.variant.product.team', 'items.variant.product.primaryImage']), 'Eliminado del carrito.');
    }

    /**
     * El route model binding resuelve CUALQUIER CartItem por id. Sin esta
     * comprobación, cambiar el número de la URL edita el carrito de otro:
     * IDOR de libro. Es la línea más importante de este controlador.
     */
    private function authorizeItem(CartItem $item): void
    {
        abort_unless(
            $this->resolver->current()?->is($item->cart),
            404,
        );
    }

    private function respond(Request $request, $cart, string $message): JsonResource|RedirectResponse
    {
        if ($request->expectsJson()) {
            return new CartResource($cart->load(['items.variant.product.team', 'items.variant.product.primaryImage']));
        }

        return back()->with('status', $message);
    }
}
