@extends('layouts.admin')

@section('eyebrow', 'Pedido '.$order->number)
@section('title', $order->status->label())

@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        <div class="table-responsive mb-4" style="border:1px solid var(--line)">
            <table class="table align-middle mb-0">
                <thead><tr class="eyebrow"><th>Artículo</th><th>SKU</th><th>Talla</th><th>Uds.</th><th class="text-end">Importe</th></tr></thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}<span class="d-block data small text-body-secondary">{{ $item->team_name }} · {{ $item->season }}</span></td>
                        <td class="data small">{{ $item->sku }}</td>
                        <td class="data">{{ $item->size }}</td>
                        <td class="data">{{ $item->quantity }}</td>
                        <td class="text-end data">{{ $item->lineTotal()->format() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-4 mb-4" style="border:1px solid var(--line)">
            <p class="eyebrow mb-3">Progreso del envío</p>
            @include('partials.order-timeline', ['order' => $order])
        </div>

        <p class="eyebrow mb-2">Pagos</p>
        <div class="table-responsive" style="border:1px solid var(--line)">
            <table class="table align-middle mb-0"><tbody>
                @forelse ($order->payments as $payment)
                    <tr>
                        <td class="data">{{ $payment->provider }}</td>
                        <td class="data small text-body-secondary">{{ $payment->provider_order_id }}</td>
                        <td><span class="badge bg-secondary">{{ $payment->status }}</span></td>
                        <td class="text-end data">{{ $payment->amount()->format() }}</td>
                    </tr>
                @empty
                    <tr><td class="p-3 text-body-secondary">Sin intentos de pago registrados.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="p-4 mb-4" style="border:1px solid var(--line);background:var(--pitch-900)">
            <p class="eyebrow mb-3">Cambiar estado</p>

            @if (empty($transitions))
                <p class="text-body-secondary small mb-0">{{ $order->status->label() }} es un estado final.</p>
            @else
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    @method('PATCH')
                    <label class="form-label small" for="status">Nuevo estado</label>
                    <select name="status" id="status" class="form-select mb-3" aria-label="Nuevo estado">
                        @foreach ($transitions as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                    </select>

                    <div class="mb-2">
                        <label class="form-label small" for="carrier">Transportista <span class="text-body-secondary">(obligatorio al enviar)</span></label>
                        <input class="form-control @error('carrier') is-invalid @enderror" id="carrier" name="carrier" value="{{ old('carrier', $order->carrier) }}" placeholder="Correos Express">
                        @error('carrier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="tracking_number">Localizador</label>
                        <input class="form-control data @error('tracking_number') is-invalid @enderror" id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}">
                        @error('tracking_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="tracking_url">URL del transportista</label>
                        <input type="url" class="form-control @error('tracking_url') is-invalid @enderror" id="tracking_url" name="tracking_url" value="{{ old('tracking_url', $order->tracking_url) }}" placeholder="https://...">
                        @error('tracking_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button class="btn btn-primary w-100">Actualizar y notificar</button>
                </form>
                <p class="search-hint mt-2 mb-0">El cliente recibirá un correo transaccional al cambiar el estado.</p>
            @endif
        </div>

        <div class="p-4 mb-4" style="border:1px solid var(--line)">
            <p class="eyebrow mb-2">Envío a</p>
            <address class="small text-body-secondary mb-0">
                {{ $order->shipping_address['full_name'] ?? '' }}<br>
                {{ $order->shipping_address['line1'] ?? '' }}<br>
                {{ $order->shipping_address['postal_code'] ?? '' }} {{ $order->shipping_address['city'] ?? '' }}<br>
                {{ $order->email }}
            </address>
        </div>

        <div class="p-4" style="border:1px solid var(--line)">
            <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Subtotal</span><span class="data">{{ $order->subtotal()->format() }}</span></div>
            @if ($order->discount_cents > 0)<div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Descuento</span><span class="data">−{{ $order->discount()->format() }}</span></div>@endif
            <div class="d-flex justify-content-between py-1"><span class="text-body-secondary">Envío</span><span class="data">{{ $order->shipping()->format() }}</span></div>
            <div class="d-flex justify-content-between align-items-baseline pt-2" style="border-top:1px solid var(--line)">
                <span class="eyebrow">Total</span><span class="price fs-4">{{ $order->total()->format() }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
