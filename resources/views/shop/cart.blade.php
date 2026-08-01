@extends('layouts.app')

@section('title', 'Tu carrito')

@section('content')
@php
    $subtotalCents = $cart?->subtotal()->cents ?? 0;
    $payableCents = max(0, $subtotalCents - ($discountCents ?? 0));
    $shippingProgress = min(100, (int) round(($payableCents / \App\Actions\Order\PlaceOrder::FREE_SHIPPING_FROM_CENTS) * 100));
@endphp

<div class="container py-4 py-lg-5 cart-page">
    <div class="cart-page__head">
        <div>
            <p class="eyebrow mb-1">Carrito</p>
            <h1 class="h2 mb-1">Tu selección</h1>
            @if ($cart && $cart->items->isNotEmpty())
                <p class="text-body-secondary mb-0">{{ $cart->itemCount() }} {{ Str::plural('artículo', $cart->itemCount()) }} reservado en tu carrito.</p>
            @endif
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-outline-light">Seguir comprando</a>
    </div>

    @if (! $cart || $cart->items->isEmpty())
        <div class="cart-empty cart-empty--powerful">
            <div class="kit-number">00</div>
            <h2 class="h4">El vestuario está vacío</h2>
            <p class="text-body-secondary mt-2 mb-4">Añade una equipación para comenzar tu pedido.</p>
            <a href="{{ route('products.index') }}" class="btn btn-primary btn-lg">Explorar catálogo</a>
        </div>
    @else
        <div class="cart-commerce-grid">
            <section class="cart-commerce-list" aria-label="Productos del carrito">
                @foreach ($cart->items as $item)
                    <article class="cart-product-card">
                        <a href="{{ route('products.show', $item->variant->product) }}" class="cart-product-card__media">
                            <img src="{{ $item->variant->product->primaryImage?->url() ?? asset('images/placeholder.svg') }}"
                                 alt="{{ $item->variant->product->name }}">
                        </a>

                        <div class="cart-product-card__info">
                            <p class="eyebrow mb-1">{{ $item->variant->product->team->name }} · {{ $item->variant->product->season }}</p>
                            <h2 class="cart-product-card__title">
                                <a href="{{ route('products.show', $item->variant->product) }}">{{ $item->variant->product->name }}</a>
                            </h2>
                            <div class="cart-product-card__meta">
                                <span>Talla <strong>{{ $item->variant->size->value }}</strong></span>
                                <span>SKU <strong>{{ $item->variant->sku }}</strong></span>
                                <span>Unidad <strong>{{ $item->variant->price()->format() }}</strong></span>
                            </div>

                            <div class="cart-product-card__actions">
                                <form method="POST" action="{{ route('cart.items.update', $item) }}" class="cart-quantity-form">
                                    @csrf
                                    @method('PATCH')
                                    <label for="qty-{{ $item->id }}">Cantidad</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="quantity" id="qty-{{ $item->id }}"
                                               value="{{ $item->quantity }}" min="0" max="10" class="form-control">
                                        <button class="btn btn-outline-light">Actualizar</button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('cart.items.destroy', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link btn-sm text-body-secondary px-0">Eliminar</button>
                                </form>
                            </div>
                        </div>

                        <div class="cart-product-card__price">
                            <span>Total</span>
                            <strong>{{ $item->lineTotal()->format() }}</strong>
                            @if ($item->variant->stock <= 3)
                                <small>Solo quedan {{ $item->variant->stock }}</small>
                            @else
                                <small>En stock</small>
                            @endif
                        </div>
                    </article>
                @endforeach

                @error('quantity')
                    <div class="alert alert-warning mt-3">{{ $message }}</div>
                @enderror

                <div class="cart-service-strip">
                    <article><strong>Pago seguro</strong><span>Importes calculados en el servidor</span></article>
                    <article><strong>30 días</strong><span>Para tramitar una devolución</span></article>
                    <article><strong>Seguimiento</strong><span>Enlace firmado por correo</span></article>
                </div>
            </section>

            <aside class="cart-summary-pro">
                <div class="cart-summary-pro__card">
                    <p class="eyebrow mb-3">Resumen del pedido</p>

                    <div class="shipping-goal {{ $freeShippingRemainingCents === 0 ? 'is-complete' : '' }}">
                        <div class="shipping-goal__copy">
                            @if ($freeShippingRemainingCents === 0)
                                <strong>Envío gratuito desbloqueado</strong>
                                <span>Tu pedido supera el mínimo requerido.</span>
                            @else
                                <strong>Te faltan {{ number_format($freeShippingRemainingCents / 100, 2, ',', '.') }} €</strong>
                                <span>para obtener envío gratuito.</span>
                            @endif
                        </div>
                        <div class="shipping-goal__track"><span style="width:{{ $shippingProgress }}%"></span></div>
                    </div>

                    <div class="coupon-pro">
                        @if ($coupon)
                            <div class="coupon-pro__applied">
                                <div><span>Cupón aplicado</span><strong>{{ $coupon->code }}</strong></div>
                                <form method="POST" action="{{ route('coupons.destroy') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link btn-sm">Quitar</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('coupons.store') }}">
                                @csrf
                                <label class="form-label eyebrow" for="cart_coupon_code">Código promocional</label>
                                <div class="input-group">
                                    <input type="text" id="cart_coupon_code" name="code" maxlength="32"
                                           class="form-control text-uppercase data @error('code') is-invalid @enderror"
                                           placeholder="XI10" required>
                                    <button class="btn btn-outline-light">Aplicar</button>
                                </div>
                                @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </form>
                        @endif
                    </div>

                    <dl class="cart-totals">
                        <div><dt>Subtotal</dt><dd>{{ $cart->subtotal()->format() }}</dd></div>
                        @if ($coupon && $discountCents > 0)
                            <div class="cart-totals__discount"><dt>Descuento {{ $coupon->code }}</dt><dd>−{{ number_format($discountCents / 100, 2, ',', '.') }} €</dd></div>
                        @endif
                        <div><dt>Envío</dt><dd>{{ $shippingCents === 0 ? 'Gratis' : number_format($shippingCents / 100, 2, ',', '.').' €' }}</dd></div>
                        <div class="cart-totals__total"><dt>Total</dt><dd>{{ number_format($totalCents / 100, 2, ',', '.') }} €</dd></div>
                    </dl>

                    <a href="{{ route('checkout.show') }}" class="btn btn-primary btn-lg w-100">Finalizar compra</a>
                    <p class="cart-summary-pro__secure mb-0">El descuento y el total se vuelven a validar dentro de la transacción del pedido.</p>
                </div>
            </aside>
        </div>
    @endif
</div>
@endsection
