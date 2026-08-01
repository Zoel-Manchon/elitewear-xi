@extends('layouts.app')

@section('title', 'Pedido '.$order->number.' confirmado')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="container py-5" style="max-width:680px">
    <div class="text-center mb-5">
        <div class="kit-number kit-number--solid" style="font-size:5rem;color:var(--grass)">✓</div>
        <p class="eyebrow mt-3 mb-2">Pedido {{ $order->number }}</p>
        <h1 class="h2 mb-3">Gracias, {{ $order->shipping_address['full_name'] ?? '' }}</h1>
        <p class="text-body-secondary">
            Te hemos enviado la confirmación a {{ $order->email }}.
            Estado actual: <strong>{{ $order->status->label() }}</strong>.
        </p>
    </div>

    <div class="p-4 order-summary-card">
        @foreach ($order->items as $item)
            <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--line)">
                <span>
                    {{ $item->product_name }}
                    <span class="data small text-body-secondary d-block">{{ $item->team_name }} · {{ $item->season }} · Talla {{ $item->size }} × {{ $item->quantity }}</span>
                </span>
                <span class="data">{{ $item->lineTotal()->format() }}</span>
            </div>
        @endforeach
        <div class="d-flex justify-content-between align-items-baseline pt-3">
            <span class="eyebrow">Total</span><span class="price fs-4">{{ $order->total()->format() }}</span>
        </div>
    </div>

    <div class="d-grid d-sm-flex gap-2 mt-4">
        <a href="{{ route('tracking.show', $order) }}" class="btn btn-primary">Seguir pedido</a>
        @auth
            <a href="{{ route('account.orders') }}" class="btn btn-outline-light">Mis pedidos</a>
        @else
            <a href="{{ route('register') }}" class="btn btn-outline-light">Crear cuenta para próximas compras</a>
        @endauth
        <a href="{{ route('products.index') }}" class="btn btn-link">Seguir mirando</a>
    </div>
</div>
@endsection
