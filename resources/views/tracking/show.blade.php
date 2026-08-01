@extends('layouts.app')

@section('title', 'Seguimiento '.$order->number)
@section('meta_description', 'Estado actualizado del pedido '.$order->number.'.')
@section('canonical', route('tracking.show', $order))
@section('robots', 'noindex,nofollow')

@section('content')
<div class="container py-4 py-lg-5" style="max-width:900px">
    <a href="{{ route('tracking.index') }}" class="small text-body-secondary">← Consultar otro pedido</a>

    <div class="d-md-flex justify-content-between align-items-end mt-3 mb-4">
        <div>
            <p class="eyebrow mb-1">Pedido {{ $order->number }}</p>
            <h1 class="h2 mb-1">{{ $order->status->label() }}</h1>
            <p class="text-body-secondary mb-0">Realizado el {{ $order->placed_at?->format('d/m/Y') }}</p>
        </div>
        <span class="badge {{ $order->status->badgeClass() }} fs-6 mt-3 mt-md-0">{{ $order->status->label() }}</span>
    </div>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            @include('partials.order-timeline', ['order' => $order])

            <div class="mt-5">
                <p class="eyebrow mb-2">Artículos</p>
                @foreach ($order->items as $item)
                    <div class="d-flex justify-content-between py-3" style="border-bottom:1px solid var(--line)">
                        <span>
                            {{ $item->product_name }}
                            <span class="d-block data small text-body-secondary">{{ $item->season }} · Talla {{ $item->size }} × {{ $item->quantity }}</span>
                        </span>
                        <span class="data">{{ $item->lineTotal()->format() }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-lg-5">
            <div class="p-4 mb-4 order-summary-card">
                <p class="eyebrow mb-3">Resumen</p>
                <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Subtotal</span><span class="data">{{ $order->subtotal()->format() }}</span></div>
                @if ($order->discount_cents > 0)
                    <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Descuento</span><span class="data">−{{ $order->discount()->format() }}</span></div>
                @endif
                <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Envío</span><span class="data">{{ $order->shipping()->format() }}</span></div>
                <div class="d-flex justify-content-between align-items-baseline pt-3 mt-2" style="border-top:1px solid var(--line)">
                    <span class="eyebrow">Total</span><span class="price fs-3">{{ $order->total()->format() }}</span>
                </div>
                @if ($order->status === \App\Enums\OrderStatus::Pending)
                    <a href="{{ route('checkout.payment', $order) }}" class="btn btn-primary w-100 mt-3">Completar pago</a>
                @endif
            </div>

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
