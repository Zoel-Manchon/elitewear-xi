<div id="catalog-results" data-catalog-results data-result-count="{{ $products->total() }}">
    <div class="catalog-results-head">
        <p class="mb-0">
            <strong>{{ $products->total() }}</strong>
            {{ Str::plural('camiseta', $products->total()) }}
        </p>
        @if ($products->total() > 0)
            <span>
                Mostrando {{ $products->firstItem() }}–{{ $products->lastItem() }}
            </span>
        @endif
    </div>

    @if ($products->isEmpty())
        <div class="catalog-empty text-center py-5">
            <div class="kit-number" style="font-size:5rem">00</div>
            <p class="text-body-secondary mt-3 mb-3">
                No hay camisetas que coincidan con esta combinación de filtros.
            </p>
            <a href="{{ route('products.index') }}" class="btn btn-outline-dark" data-catalog-clear>
                Ver catálogo completo
            </a>
        </div>
    @else
        <div class="row g-3 g-md-4">
            @foreach ($products as $product)
                <div class="col-6 col-md-4 col-xl-3">
                    <x-product-card :product="$product"/>
                </div>
            @endforeach
        </div>

        @if ($products->hasPages())
            <nav class="mt-5" aria-label="Paginación del catálogo" data-catalog-pagination>
                {{ $products->links() }}
            </nav>
        @endif
    @endif
</div>
