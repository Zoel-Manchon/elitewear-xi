@extends('layouts.app')

@section('title', 'Camisetas de fútbol')

@section('content')
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__content">
            <p class="eyebrow mb-3">{{ $hero?->team->name ?? 'Real Madrid CF' }} · {{ $hero?->season ?? '2019-2020' }}</p>

            <h1 class="hero__title">
                Tu equipo.<br>Tu camiseta.
            </h1>

            <p class="hero__lede">
                Camisetas de clubes y selecciones con imágenes claras, precios finales
                y stock comprobado talla a talla. Compra directa, clara y sin distracciones.
            </p>

            <div class="hero__actions">
                <a href="{{ route('products.index') }}" class="btn btn-primary btn-lg">
                    Ver el catálogo
                </a>

                @if ($hero)
                    <a href="{{ route('products.show', $hero) }}" class="btn btn-outline-dark btn-lg">
                        Ver esta camiseta
                    </a>
                @endif
            </div>

            <dl class="hero__facts">
                <div>
                    <dt>En catálogo</dt>
                    <dd>{{ $catalogCount ?? $newest->count() }} camisetas</dd>
                </div>
                <div>
                    <dt>Envío</dt>
                    <dd>Gratis desde 100 €</dd>
                </div>
                <div>
                    <dt>Devolución</dt>
                    <dd>30 días</dd>
                </div>
            </dl>
        </div>

        <div class="hero__stage">

            @if ($hero)
                <a href="{{ route('products.show', $hero) }}" class="hero__shirt-link">
                    <img src="{{ $hero->primaryImage?->url() ?? asset('images/hero-real-madrid-2019-2020.png') }}"
                         alt="{{ $hero->name }}" class="hero__shirt"
                         fetchpriority="high" decoding="async">
                </a>
            @else
                <div class="hero__shirt-link">
                    <img src="{{ asset('images/hero-real-madrid-2019-2020.png') }}"
                         alt="Camiseta local del Real Madrid 2019-2020" class="hero__shirt"
                         fetchpriority="high" decoding="async">
                </div>
            @endif

            <div class="hero__tag">
                <span class="hero__tag-name">{{ $hero?->name ?? 'Real Madrid CF · Local 2019-2020' }}</span>
                @if ($hero)
                    <span class="hero__tag-price price">{{ $hero->basePrice()->format() }}</span>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="band-light">
    <div class="container">
    <div class="section-head">
        <div>
            <p class="eyebrow mb-1">Recién catalogadas</p>
            <h2 class="h3 mb-0">Novedades</h2>
        </div>
        <a href="{{ route('products.index') }}" class="d-inline-flex align-items-center gap-2 data">
            Ver todas <svg width="18" height="18"><use href="#icon-arrow"/></svg>
        </a>
    </div>

    <div class="row g-3 g-md-4">
        @foreach ($newest as $product)
            <div class="col-6 col-md-4 col-lg-3">
                <x-product-card :product="$product"/>
            </div>
        @endforeach
    </div>
    </div>
</section>

<section class="container pb-5">
    <div class="section-head">
        <div>
            <p class="eyebrow mb-1">Entrar por una historia</p>
            <h2 class="h3 mb-0">Colecciones</h2>
        </div>
    </div>

    <div class="collection-grid">
        <a class="collection-card collection-card--wide"
           href="{{ route('products.index', ['tipo' => 'clubes']) }}">
            <span class="collection-card__number" aria-hidden="true">XI</span>
            <span class="eyebrow">Ligas nacionales y competiciones europeas</span>
            <strong>Clubes</strong>
            <span class="collection-card__link">Explorar equipos <svg width="18" height="18"><use href="#icon-arrow"/></svg></span>
        </a>

        <a class="collection-card" href="{{ route('products.index', ['tipo' => 'selecciones']) }}">
            <span class="collection-card__number" aria-hidden="true">10</span>
            <span class="eyebrow">Torneos internacionales</span>
            <strong>Selecciones</strong>
            <span class="collection-card__link">Ver equipaciones <svg width="18" height="18"><use href="#icon-arrow"/></svg></span>
        </a>

        <a class="collection-card" href="{{ route('products.index', ['orden' => 'novedades']) }}">
            <span class="collection-card__number" aria-hidden="true">01</span>
            <span class="eyebrow">Últimas incorporaciones</span>
            <strong>Novedades</strong>
            <span class="collection-card__link">Ver lo nuevo <svg width="18" height="18"><use href="#icon-arrow"/></svg></span>
        </a>
    </div>
</section>

<section class="container pb-2">
    <div class="section-head">
        <h2 class="h5 mb-0">Explorar el catálogo</h2>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @foreach (['europa' => 'Europa', 'america' => 'América', 'asia' => 'Asia', 'africa' => 'África'] as $slug => $label)
            <a href="{{ route('products.index', ['region' => $slug]) }}" class="chip">{{ $label }}</a>
        @endforeach
        @foreach (['clubes' => 'Clubes', 'selecciones' => 'Selecciones'] as $slug => $label)
            <a href="{{ route('products.index', ['tipo' => $slug]) }}" class="chip">{{ $label }}</a>
        @endforeach
    </div>
</section>

@if ($teams->isNotEmpty())
    <section class="container pb-5">
        <div class="section-head">
            <h2 class="h5 mb-0">Por equipo</h2>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($teams as $team)
                <a href="{{ route('products.index', ['equipo' => $team->slug]) }}" class="chip">
                    {{ $team->name }}
                    <span style="opacity:.55">{{ $team->products_count }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif

<section class="container pb-5">
    <div class="trust-grid">
        @foreach ([
            ['01', 'Envío en 24-48 h', 'A península. Gratis a partir de 100 €.'],
            ['02', '30 días para devolver', 'Sin explicaciones y con recogida incluida.'],
            ['03', 'Cada talla, contada', 'El stock que ves es el que hay, unidad a unidad.'],
        ] as [$number, $title, $text])
            <article class="trust-item">
                <span class="trust-item__number">{{ $number }}</span>
                <div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
