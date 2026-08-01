@extends('layouts.app')

@section('title', 'Mis pedidos')

@section('content')
<div class="container py-4 py-lg-5">
    <p class="eyebrow mb-1">Tu cuenta</p>
    <h1 class="h2 mb-4">Mis pedidos</h1>

    @if ($orders->isEmpty())
        <div class="text-center py-5" style="border:1px solid var(--line)">
            <p class="text-body-secondary mb-3">Todavía no has hecho ningún pedido.</p>
            <a href="{{ route('products.index') }}" class="btn btn-primary">Ver el catálogo</a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="eyebrow">
                        <th>Pedido</th><th>Fecha</th><th>Artículos</th><th>Estado</th><th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td><a href="{{ route('account.orders.show', $order) }}" class="data">{{ $order->number }}</a></td>
                        <td class="data text-body-secondary">{{ $order->created_at->format('d/m/Y') }}</td>
                        <td class="data">{{ $order->items_count }}</td>
                        <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                        <td class="text-end price">{{ $order->total()->format() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
