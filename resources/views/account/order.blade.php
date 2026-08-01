@extends('layouts.app')

@section('title', 'Pedido '.$order->number)
@section('robots', 'noindex,nofollow')

@section('content')
<div class="container py-4 py-lg-5" style="max-width:920px">
    <a href="{{ route('account.orders') }}" class="small text-body-secondary">← Todos mis pedidos</a>

    <div class="d-flex justify-content-between align-items-end mt-3 mb-4">
        <div>
            <p class="eyebrow mb-1">Pedido {{ $order->number }}</p>
            <h1 class="h3 mb-0">{{ $order->created_at->format('d/m/Y') }}</h1>
        </div>
        <span class="badge {{ $order->status->badgeClass() }} fs-6">{{ $order->status->label() }}</span>
    </div>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            @include('partials.order-timeline', ['order' => $order])

            <div class="mt-5">
                <p class="eyebrow mb-2">Artículos</p>
                @foreach ($order->items as $item)
                    <div class="d-flex justify-content-between align-items-start gap-3 py-3" style="border-bottom:1px solid var(--line)">
                        <span>
                            {{ $item->product_name }}
                            <span class="data small text-body-secondary d-block">{{ $item->season }} · Talla {{ $item->size }} × {{ $item->quantity }}</span>
                            @if ($item->product && in_array($order->status, [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Shipped, \App\Enums\OrderStatus::Delivered], true))
                                <a class="small" href="{{ route('products.show', $item->product) }}#resenas">Escribir reseña verificada</a>
                            @endif
                        </span>
                        <span class="data">{{ $item->lineTotal()->format() }}</span>
                    </div>
                @endforeach

                <div class="d-flex justify-content-between py-2"><span class="text-body-secondary">Envío</span><span class="data">{{ $order->shipping()->format() }}</span></div>
                <div class="d-flex justify-content-between align-items-baseline pt-2" style="border-top:1px solid var(--line)">
                    <span class="eyebrow">Total</span><span class="price fs-4">{{ $order->total()->format() }}</span>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="p-4 order-summary-card">
                <p class="eyebrow mb-2">Envío a</p>
                <address class="small text-body-secondary mb-0">
                    {{ $order->shipping_address['full_name'] ?? '' }}<br>
                    {{ $order->shipping_address['line1'] ?? '' }}<br>
                    @if (! empty($order->shipping_address['line2'])){{ $order->shipping_address['line2'] }}<br>@endif
                    {{ $order->shipping_address['postal_code'] ?? '' }} {{ $order->shipping_address['city'] ?? '' }}
                </address>
            </div>
        </div>
    </div>
</div>
@endsection
