@props(['product'])

@php
    $stock = $product->stock_total ?? null;
@endphp

<article class="shirt-card h-100 d-flex flex-column" data-product-card="{{ $product->id }}">
    <div class="shirt-card__media">
        <a href="{{ route('products.show', $product) }}" class="shirt-card__media-link" aria-label="Ver {{ $product->name }}">

            <img src="{{ $product->primaryImage?->url() ?? asset('images/placeholder.svg') }}"
                 alt="{{ $product->primaryImage?->alt ?? $product->name }}" loading="lazy">

            @isset($stock)
                @if ($stock == 0)
                    <span class="shirt-card__flag shirt-card__flag--out">Agotada</span>
                @elseif ($stock <= 5)
                    <span class="shirt-card__flag shirt-card__flag--low">Últimas {{ $stock }}</span>
                @endif
            @endisset

        </a>

        <button type="button" class="wishlist-toggle" data-wishlist-toggle
                data-product-id="{{ $product->id }}" aria-pressed="false"
                aria-label="Guardar {{ $product->name }} en favoritos">
            <svg width="20" height="20" aria-hidden="true"><use href="#icon-heart"/></svg>
        </button>
    </div>

    <div class="shirt-card__body mt-auto">
        <div class="shirt-card__team">{{ $product->team->name }}</div>
        <h3 class="shirt-card__name">
            <a href="{{ route('products.show', $product) }}">
                {{ $product->season ?: 'Temporada no indicada' }} · {{ $product->kit_type->label() }}
            </a>
        </h3>
        <div class="shirt-card__foot">
            <div class="price">{{ $product->basePrice()->format() }}</div>
            <a class="shirt-card__cta" href="{{ route('products.show', $product) }}">
                <span>Ver ficha</span>
                <svg width="16" height="16" aria-hidden="true"><use href="#icon-arrow"/></svg>
            </a>
        </div>
    </div>
</article>
