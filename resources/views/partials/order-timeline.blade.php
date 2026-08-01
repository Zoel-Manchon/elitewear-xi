@php
    $steps = [
        ['label' => 'Pedido recibido', 'date' => $order->placed_at, 'done' => (bool) $order->placed_at],
        ['label' => 'Pago confirmado', 'date' => $order->paid_at, 'done' => (bool) $order->paid_at],
        ['label' => 'Enviado', 'date' => $order->shipped_at, 'done' => (bool) $order->shipped_at],
        ['label' => 'Entregado', 'date' => $order->delivered_at, 'done' => (bool) $order->delivered_at],
    ];
@endphp

@if (in_array($order->status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::Refunded], true))
    <div class="order-exception mb-4">
        <strong>{{ $order->status->label() }}</strong>
        <span>El pedido no seguirá el flujo normal de entrega.</span>
    </div>
@endif

<ol class="order-timeline" aria-label="Progreso del pedido">
    @foreach ($steps as $step)
        <li class="order-timeline__step {{ $step['done'] ? 'is-done' : '' }}">
            <span class="order-timeline__marker" aria-hidden="true">
                @if ($step['done'])
                    <svg width="15" height="15"><use href="#icon-check"/></svg>
                @endif
            </span>
            <div>
                <strong>{{ $step['label'] }}</strong>
                <span>{{ $step['date']?->format('d/m/Y · H:i') ?? 'Pendiente' }}</span>
            </div>
        </li>
    @endforeach
</ol>

@if ($order->tracking_number)
    <div class="tracking-card mt-4">
        <svg width="22" height="22" aria-hidden="true"><use href="#icon-truck"/></svg>
        <div class="flex-grow-1">
            <div class="eyebrow mb-1">Seguimiento del transportista</div>
            <div class="fw-semibold">{{ $order->carrier ?: 'Transportista' }}</div>
            <div class="data text-body-secondary">{{ $order->tracking_number }}</div>
        </div>
        @if ($order->tracking_url)
            <a class="btn btn-outline-light btn-sm" href="{{ $order->tracking_url }}" rel="noopener noreferrer" target="_blank">
                Abrir seguimiento
            </a>
        @endif
    </div>
@endif
