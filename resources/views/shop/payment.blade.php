@extends('layouts.app')

@section('title', 'Pago del pedido '.$order->number)
@section('robots', 'noindex,nofollow')

@section('content')
<div class="container py-4 py-lg-5" style="max-width:720px">
    <p class="eyebrow mb-1">Paso 2 de 2</p>
    <h1 class="h2 mb-4">Pagar el pedido</h1>

    <div class="p-4 mb-4" style="border:1px solid var(--line);background:var(--pitch-900)">
        <div class="d-flex justify-content-between align-items-baseline mb-3">
            <span class="data text-body-secondary">Pedido {{ $order->number }}</span>
            <span class="price fs-3">{{ $order->total()->format() }}</span>
        </div>

        @foreach ($order->items as $item)
            <div class="d-flex justify-content-between small py-1">
                <span>{{ $item->product_name }} <span class="data text-body-secondary">· {{ $item->size }} × {{ $item->quantity }}</span></span>
                <span class="data">{{ number_format($item->line_total_cents / 100, 2, ',', '.') }} €</span>
            </div>
        @endforeach
    </div>

    <div id="paypal-buttons" data-order="{{ $order->number }}"
         data-create="{{ route('payments.create', $order) }}"
         data-capture="{{ route('payments.capture', $order) }}"></div>

    <div id="payment-error" class="alert alert-danger d-none mt-3" role="alert"></div>

    <p class="search-hint mt-4">
        Entorno de pruebas de PayPal. No se cobra dinero real.
    </p>
</div>
@endsection

@push('scripts')
    @if ($paypalClientId)
        <script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency=EUR" defer></script>
    @endif
@endpush
