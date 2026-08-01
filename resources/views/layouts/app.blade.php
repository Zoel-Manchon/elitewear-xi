@php
    $pageTitle = trim($__env->yieldContent('title', 'Camisetas de fútbol'));
    $pageDescription = trim($__env->yieldContent('meta_description', 'Camisetas de fútbol de clubes y selecciones, catalogadas por equipo, temporada y disponibilidad real.'));
    $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
    $ogImage = trim($__env->yieldContent('og_image')) ?: asset('images/placeholder.svg');
    $ogType = trim($__env->yieldContent('og_type', 'website'));
    $catalogTypes = collect(\Illuminate\Support\Arr::wrap(request('tipo')))->filter()->all();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} · Elitewear XI</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="{{ $canonical }}">

    <meta property="og:locale" content="es_ES">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="Elitewear XI">
    <meta property="og:title" content="{{ $pageTitle }} · Elitewear XI">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }} · Elitewear XI">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#0f172a">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Elitewear XI',
        'url' => route('home'),
        'logo' => asset('images/placeholder.svg'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @stack('structured_data')
</head>
<body class="d-flex flex-column min-vh-100" data-authenticated="{{ auth()->check() ? 'true' : 'false' }}">

@include('partials.icons')

<a href="#contenido" class="skip-link">Saltar al contenido</a>

<div class="announce">
    <div class="container announce__inner">
        <span>Envío gratis a partir de 100 €</span>
        <span class="announce__sep" aria-hidden="true">·</span>
        <span>Devolución en 30 días</span>
        <span class="announce__sep d-none d-md-inline" aria-hidden="true">·</span>
        <span class="d-none d-md-inline">Compra sin crear cuenta</span>
    </div>
</div>

<header class="site-header">
    <div class="container site-header__bar">
        @include('partials.brand')

        <nav class="site-header__nav" aria-label="Principal">
            <a class="{{ request()->routeIs('products.*') && $catalogTypes === [] ? 'is-on' : '' }}"
               href="{{ route('products.index') }}">Catálogo</a>
            <a class="{{ in_array('clubes', $catalogTypes, true) ? 'is-on' : '' }}"
               href="{{ route('products.index', ['tipo' => ['clubes']]) }}">Clubes</a>
            <a class="{{ in_array('selecciones', $catalogTypes, true) ? 'is-on' : '' }}"
               href="{{ route('products.index', ['tipo' => ['selecciones']]) }}">Selecciones</a>
            <a href="{{ route('products.index', ['orden' => 'novedades']) }}">Novedades</a>
        </nav>

        <div class="site-header__actions">
            <button class="icon-btn" type="button" data-search-open aria-label="Buscar camisetas">
                <svg width="20" height="20"><use href="#icon-search"/></svg>
                <kbd class="d-none d-xl-inline">/</kbd>
            </button>

            <a class="icon-btn" href="{{ route('wishlist.index') }}" aria-label="Favoritos">
                <svg width="20" height="20"><use href="#icon-heart"/></svg>
                <span class="cart-count d-none" data-wishlist-count>0</span>
            </a>

            @auth
                <div class="dropdown">
                    <button class="icon-btn" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false" aria-label="Tu cuenta">
                        <svg width="20" height="20"><use href="#icon-user"/></svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header data">{{ Auth::user()->name }}</li>
                        <li><a class="dropdown-item" href="{{ route('account.orders') }}">Mis pedidos</a></li>
                        <li><a class="dropdown-item" href="{{ route('wishlist.index') }}">Favoritos</a></li>
                        @if (Auth::user()->is_admin)
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Panel de admin</a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <a class="icon-btn" href="{{ route('login') }}" aria-label="Entrar en tu cuenta">
                    <svg width="20" height="20"><use href="#icon-user"/></svg>
                </a>
            @endauth

            <button class="icon-btn icon-btn--cart" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#cartDrawer"
                    aria-controls="cartDrawer" aria-label="Abrir el carrito">
                <svg width="20" height="20"><use href="#icon-bag"/></svg>
                <span class="cart-count {{ ($cartCount ?? 0) > 0 ? '' : 'd-none' }}"
                      data-cart-count>{{ $cartCount ?? 0 }}</span>
            </button>

            <button class="icon-btn d-lg-none" type="button"
                    data-bs-toggle="collapse" data-bs-target="#mobileNav"
                    aria-controls="mobileNav" aria-expanded="false" aria-label="Abrir el menú">
                <svg width="20" height="20"><use href="#icon-menu"/></svg>
            </button>
        </div>
    </div>

    <div class="collapse d-lg-none" id="mobileNav">
        <nav class="container site-header__mobile" aria-label="Principal, móvil">
            <a href="{{ route('products.index') }}">Catálogo</a>
            <a href="{{ route('products.index', ['tipo' => ['clubes']]) }}">Clubes</a>
            <a href="{{ route('products.index', ['tipo' => ['selecciones']]) }}">Selecciones</a>
            <a href="{{ route('products.index', ['orden' => 'novedades']) }}">Novedades</a>
            <a href="{{ route('wishlist.index') }}">Favoritos</a>
            <a href="{{ route('tracking.index') }}">Seguir pedido</a>
            @auth
                <a href="{{ route('account.orders') }}">Mis pedidos</a>
            @else
                <a href="{{ route('login') }}">Entrar</a>
                <a href="{{ route('register') }}">Crear cuenta</a>
            @endauth
        </nav>
    </div>
</header>

@include('partials.search-overlay')
@include('partials.cart-drawer')

<main id="contenido" class="flex-grow-1">
    @if (session('status'))
        <div class="container mt-3">
            <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
        </div>
    @endif

    @yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-5">
                @include('partials.brand')
                <p class="text-body-secondary mt-3 mb-0" style="max-width:36ch">
                    Camisetas de clubes y selecciones con información clara de
                    equipo, temporada y unidades disponibles.
                </p>
            </div>

            <div class="col-6 col-lg-2">
                <p class="eyebrow mb-2">Comprar</p>
                <a class="site-footer__link" href="{{ route('products.index') }}">Todo el catálogo</a>
                <a class="site-footer__link" href="{{ route('wishlist.index') }}">Favoritos</a>
                <a class="site-footer__link" href="{{ route('tracking.index') }}">Seguir pedido</a>
            </div>

            <div class="col-6 col-lg-2">
                <p class="eyebrow mb-2">Cuenta</p>
                @auth
                    <a class="site-footer__link" href="{{ route('account.orders') }}">Mis pedidos</a>
                @else
                    <a class="site-footer__link" href="{{ route('login') }}">Entrar</a>
                    <a class="site-footer__link" href="{{ route('register') }}">Crear cuenta</a>
                @endauth
            </div>

            <div class="col-lg-3">
                <p class="eyebrow mb-2">Para desarrolladores</p>
                <a class="site-footer__link" href="/api/v1/products">API pública v1</a>
                <a class="site-footer__link" href="{{ route('sitemap') }}">Sitemap XML</a>
                <p class="search-hint mt-2 mb-0">Catálogo en JSON, versionado y documentado.</p>
            </div>
        </div>

        <p class="site-footer__legal">
            Proyecto de portfolio · Los pagos se procesan en el entorno de pruebas de PayPal
        </p>
    </div>
</footer>

@stack('scripts')

</body>
</html>
