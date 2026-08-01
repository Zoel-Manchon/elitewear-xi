@extends('layouts.app')

@section('title', 'Favoritos')
@section('meta_description', 'Tus camisetas de fútbol favoritas, guardadas para consultarlas más tarde.')
@section('canonical', route('wishlist.index'))
@section('robots', 'noindex,follow')

@section('content')
<div class="container py-4 py-lg-5" data-wishlist-page>
    <div class="d-md-flex justify-content-between align-items-end mb-4">
        <div>
            <p class="eyebrow mb-1">Tu selección</p>
            <h1 class="display-6 mb-1">Favoritos</h1>
            <p class="text-body-secondary mb-0">
                @auth
                    Se guardan en tu cuenta y están disponibles en cualquier dispositivo.
                @else
                    Se guardan en este navegador. Al iniciar sesión se añadirán automáticamente a tu cuenta.
                @endauth
            </p>
        </div>
        <span class="data text-body-secondary mt-3 mt-md-0" data-wishlist-page-count></span>
    </div>

    <div class="row g-3 g-md-4" data-wishlist-grid>
        @auth
            @foreach ($products as $product)
                <div class="col-6 col-lg-3" data-wishlist-grid-item="{{ $product->id }}">
                    <x-product-card :product="$product"/>
                </div>
            @endforeach
        @endauth
    </div>

    <div class="wishlist-empty text-center py-5 {{ $products->isNotEmpty() ? 'd-none' : '' }}" data-wishlist-empty>
        <div class="kit-number mb-3" aria-hidden="true">0</div>
        <h2 class="h4">Aún no has guardado ninguna</h2>
        <p class="text-body-secondary">Pulsa el corazón de una camiseta para encontrarla aquí.</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary">Explorar catálogo</a>
    </div>
</div>
@endsection
