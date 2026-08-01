<div class="offcanvas offcanvas-end" tabindex="-1" id="cartDrawer" aria-labelledby="cartDrawerTitle">
    <div class="offcanvas-header border-bottom" style="border-color:var(--line)!important">
        <h2 class="offcanvas-title h6 eyebrow mb-0" id="cartDrawerTitle">Tu carrito</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                aria-label="Cerrar"></button>
    </div>

    {{-- Estado inicial renderizado en servidor; cart.js lo sustituye al cargar --}}
    <div class="offcanvas-body py-0" data-cart-body>
        @if (($cartCount ?? 0) === 0)
            <div class="cart-empty">
                <div class="kit-number">0</div>
                <p class="text-body-secondary mb-3">Todavía no has elegido ninguna camiseta.</p>
                <a href="{{ route('products.index') }}" class="btn btn-primary">Ver el catálogo</a>
            </div>
        @else
            @foreach ($cart->items as $item)
                <div class="cart-line">
                    <img src="{{ $item->variant->product->primaryImage?->url() ?? asset('images/placeholder.svg') }}" alt="">
                    <div>
                        <div class="shirt-card__team">{{ $item->variant->product->team->name }}</div>
                        <div class="fw-semibold">{{ $item->variant->product->name }}</div>
                        <div class="data small text-body-secondary">
                            Talla {{ $item->variant->size->value }} · {{ $item->quantity }} ud.
                        </div>
                    </div>
                    <div class="price">{{ $item->lineTotal()->format() }}</div>
                </div>
            @endforeach
        @endif
    </div>

    <div class="offcanvas-header d-block border-top" style="border-color:var(--line)!important"
         data-cart-footer>
        @if (($cartCount ?? 0) > 0)
            <div class="d-flex justify-content-between align-items-baseline py-2">
                <span class="eyebrow">Subtotal</span>
                <span class="price fs-4">{{ $cart->subtotal()->format() }}</span>
            </div>
            <div class="d-grid gap-2">
                <a href="{{ route('checkout.show') }}" class="btn btn-primary btn-lg">Finalizar compra</a>
                <a href="{{ route('cart.index') }}" class="btn btn-outline-light btn-sm">Ver el carrito completo</a>
            </div>
        @endif
    </div>
</div>
