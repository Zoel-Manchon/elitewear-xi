<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · Elitewear XI</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
<div class="admin-shell">

    <aside class="admin-side d-flex flex-column">
        <a href="{{ route('home') }}" class="mb-4"
           style="font-family:var(--font-display);font-weight:900;text-transform:uppercase;color:var(--chalk)">
            Elitewear XI
        </a>

        <nav>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-on' : '' }}">Resumen</a>
            <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'is-on' : '' }}">Productos</a>
            <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'is-on' : '' }}">Pedidos</a>
            <a href="{{ route('admin.coupons.index') }}" class="{{ request()->routeIs('admin.coupons.*') ? 'is-on' : '' }}">Códigos</a>
            <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'is-on' : '' }}">Auditoría</a>
        </nav>

        <div class="mt-auto pt-4" style="border-top:1px solid var(--line)">
            <p class="data small text-body-secondary mb-1">{{ auth()->user()->name }}</p>
            <a href="{{ route('home') }}" class="p-0 small">Ver la tienda</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-link p-0 small text-body-secondary">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <p class="eyebrow mb-1">@yield('eyebrow', 'Panel')</p>
                <h1 class="h3 mb-0">@yield('title', 'Panel')</h1>
            </div>
            @yield('actions')
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
