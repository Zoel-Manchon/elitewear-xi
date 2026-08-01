<div id="catalog-active-filters" class="catalog-active-filters" data-catalog-active-filters>
    @if (count($activeFilters) > 0)
        <span class="catalog-active-filters__label">Activos:</span>
        @foreach ($activeFilters as $filter)
            <button type="button" class="catalog-active-chip"
                    data-filter-remove data-filter-name="{{ $filter['name'] }}"
                    data-filter-value="{{ $filter['value'] }}"
                    aria-label="Quitar filtro {{ $filter['label'] }}">
                {{ $filter['label'] }}
                <span aria-hidden="true">×</span>
            </button>
        @endforeach
        <a href="{{ $clearFiltersUrl }}" class="catalog-active-filters__clear" data-catalog-clear>
            Quitar todos
        </a>
    @endif
</div>
