@extends('layouts.app')

@section('title', 'Catálogo de camisetas')

@section('content')
@php
    $selectedTypes = $filters['tipo'] ?? [];
    $selectedRegions = $filters['region'] ?? [];
    $selectedCountries = $filters['pais'] ?? [];
    $clearFiltersUrl = route('products.index', array_filter([
        'orden' => ($filters['orden'] ?? 'novedades') !== 'novedades' ? $filters['orden'] : null,
    ]));
@endphp

<div class="container py-4 py-lg-5" data-catalog-page>
    <div data-catalog-team-context>
        @if ($filteredTeam ?? null)
            @include('shop.partials.team-header', ['team' => $filteredTeam])
        @endif
    </div>

    <div class="catalog-heading mb-4">
        <div>
            <p class="eyebrow mb-1">Catálogo</p>
            <h1 class="h2 mb-1">Encuentra tu camiseta</h1>
            <p class="text-body-secondary mb-0">
                Combina continente, país y tipo de equipo. Los resultados se actualizan sin recargar la página.
            </p>
        </div>

        <button class="btn btn-outline-dark d-lg-none" type="button"
                data-bs-toggle="collapse" data-bs-target="#catalogFilters"
                aria-expanded="false" aria-controls="catalogFilters">
            Filtros
            @if (count($activeFilters) > 0)
                <span class="catalog-filter-badge">{{ count($activeFilters) }}</span>
            @endif
        </button>
        <noscript>
            <style>
                #catalogFilters { display: block !important; }
                [data-country-filter-search] { display: none !important; }
            </style>
        </noscript>
    </div>

    <div class="catalog-layout">
        <aside class="catalog-filters collapse d-lg-block" id="catalogFilters" aria-label="Filtros del catálogo">
            <div class="catalog-filters__head">
                <div>
                    <p class="eyebrow mb-1">Filtrar</p>
                    <h2 class="h5 mb-0">Refina el catálogo</h2>
                </div>
                <a href="{{ $clearFiltersUrl }}" class="catalog-clear-link"
                   data-catalog-clear @class(['invisible' => count($activeFilters) === 0])>
                    Limpiar
                </a>
            </div>

            <form method="GET" action="{{ route('products.index') }}" id="catalog-filter-form"
                  data-catalog-filter-form>
                @if (! empty($filters['equipo']))
                    <input type="hidden" name="equipo" value="{{ $filters['equipo'] }}">
                @endif

                <div class="catalog-filter-group">
                    <label class="catalog-filter-label" for="catalog-query">Buscar</label>
                    <div class="catalog-search-field">
                        <svg width="17" height="17" aria-hidden="true"><use href="#icon-search"/></svg>
                        <input type="search" id="catalog-query" name="q" maxlength="80"
                               value="{{ $filters['q'] ?? '' }}"
                               placeholder="Equipo o temporada"
                               autocomplete="off" data-catalog-debounce>
                    </div>
                </div>

                <fieldset class="catalog-filter-group">
                    <legend class="catalog-filter-label">Tipo</legend>
                    <div class="catalog-filter-options">
                        @foreach ($typeOptions as $option)
                            <label class="catalog-filter-choice @if($option['count'] === 0) is-empty @endif">
                                <input type="checkbox" name="tipo[]" value="{{ $option['value'] }}"
                                       @checked(in_array($option['value'], $selectedTypes, true))
                                       @disabled($option['count'] === 0 && ! in_array($option['value'], $selectedTypes, true))>
                                <span>{{ $option['label'] }}</span>
                                <small>{{ $option['count'] }}</small>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="catalog-filter-group">
                    <legend class="catalog-filter-label">Continente</legend>
                    <div class="catalog-filter-options">
                        @foreach ($regionOptions as $option)
                            <label class="catalog-filter-choice @if($option['count'] === 0) is-empty @endif">
                                <input type="checkbox" name="region[]" value="{{ $option['value'] }}"
                                       @checked(in_array($option['value'], $selectedRegions, true))
                                       @disabled($option['count'] === 0 && ! in_array($option['value'], $selectedRegions, true))>
                                <span>{{ $option['label'] }}</span>
                                <small>{{ $option['count'] }}</small>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if (count($countryOptions) > 0)
                    <fieldset class="catalog-filter-group">
                        <legend class="catalog-filter-label">País</legend>
                        <input class="form-control form-control-sm mb-2" type="search"
                               placeholder="Buscar país" autocomplete="off"
                               aria-label="Buscar un país dentro de los filtros"
                               data-country-filter-search>

                        <div class="catalog-country-list" data-country-filter-list>
                            @foreach ($countryOptions as $option)
                                <label class="catalog-filter-choice"
                                       data-country-option data-country-label="{{ Str::lower($option['label']) }} {{ Str::lower($option['value']) }}">
                                    <input type="checkbox" name="pais[]" value="{{ $option['value'] }}"
                                           @checked(in_array($option['value'], $selectedCountries, true))>
                                    <span>
                                        <span class="catalog-country-code">{{ $option['value'] }}</span>
                                        {{ $option['label'] }}
                                    </span>
                                    <small>{{ $option['count'] }}</small>
                                </label>
                            @endforeach
                        </div>
                        <p class="catalog-country-empty d-none mb-0" data-country-filter-empty>
                            No hay países que coincidan.
                        </p>
                    </fieldset>
                @endif

                <fieldset class="catalog-filter-group">
                    <legend class="catalog-filter-label">Precio</legend>
                    <div class="catalog-price-fields">
                        <label>
                            <span>Desde</span>
                            <span class="catalog-price-input">
                                <input type="number" name="precio_min" min="0" step="0.01"
                                       inputmode="decimal" value="{{ $filters['precio_min'] ?? '' }}"
                                       placeholder="0" data-catalog-debounce>
                                <span>€</span>
                            </span>
                        </label>
                        <label>
                            <span>Hasta</span>
                            <span class="catalog-price-input">
                                <input type="number" name="precio_max" min="0" step="0.01"
                                       inputmode="decimal" value="{{ $filters['precio_max'] ?? '' }}"
                                       placeholder="200" data-catalog-debounce>
                                <span>€</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <noscript>
                    <button type="submit" class="btn btn-dark w-100 mt-3">Aplicar filtros</button>
                </noscript>
            </form>
        </aside>

        <section class="catalog-main" aria-label="Resultados del catálogo">
            <div class="catalog-toolbar mb-3">
                <div>
                    <p class="search-hint mb-0">Stock real y precios finales en euros</p>
                    <span class="catalog-live-status" role="status" aria-live="polite" data-catalog-status></span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label class="catalog-sort-label" for="orden">Ordenar</label>
                    <select class="form-select form-select-sm" name="orden" id="orden"
                            form="catalog-filter-form" data-catalog-sort>
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['orden'] ?? 'novedades') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @include('shop.partials.catalog-active-filters')
            @include('shop.partials.catalog-results')
        </section>
    </div>
</div>
@endsection
