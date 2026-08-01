<?php

namespace App\Http\Controllers;

use App\Actions\Order\PlaceOrder;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Address;
use App\Models\Order;
use App\Support\CartResolver;
use App\Support\CouponSession;
use App\Support\OrderAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartResolver $resolver,
        private readonly CouponSession $coupons,
        private readonly OrderAccess $access,
    ) {}

    public function show(): View|RedirectResponse
    {
        $cart = $this->resolver->current();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('products.index')->with('status', 'Tu carrito está vacío.');
        }

        $subtotal = $cart->subtotal()->cents;
        $coupon = $this->coupons->validFor($subtotal);
        $discount = $coupon?->discountFor($subtotal) ?? 0;
        $user = request()->user();

        return view('shop.checkout', [
            'cart' => $cart,
            'coupon' => $discount > 0 ? $coupon : null,
            'discountCents' => $discount,
            'address' => $user?->addresses()->where('is_default', true)->first(),
            'checkoutEmail' => $user?->email,
            'shippingCents' => ($subtotal - $discount) >= PlaceOrder::FREE_SHIPPING_FROM_CENTS
                ? 0
                : PlaceOrder::SHIPPING_CENTS,
        ]);
    }

    public function store(StoreCheckoutRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $cart = $this->resolver->current();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('products.index');
        }

        try {
            $order = $placeOrder->handle(
                $cart,
                $request->address(),
                $request->string('email')->toString(),
                $request->user()?->id,
                $this->coupons->current(),
            );
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        }

        if ($request->user() && $request->boolean('save_address')) {
            Address::updateOrCreate(
                ['user_id' => $request->user()->id, 'is_default' => true],
                $request->address() + ['user_id' => $request->user()->id, 'is_default' => true],
            );
        }

        $this->access->grant($order);
        $this->coupons->forget();

        return redirect()->route('checkout.payment', $order);
    }

    public function payment(Order $order): View|RedirectResponse
    {
        $this->access->authorize($order);

        if ($order->status !== OrderStatus::Pending) {
            return redirect()->route('checkout.confirmation', $order);
        }

        return view('shop.payment', [
            'order' => $order->load('items'),
            'paypalClientId' => config('services.paypal.client_id'),
        ]);
    }

    public function confirmation(Order $order): View
    {
        $this->access->authorize($order);

        return view('shop.confirmation', ['order' => $order->load('items')]);
    }
}
