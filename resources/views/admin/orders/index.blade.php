@extends('layouts.admin')

@section('eyebrow', 'Ventas')
@section('title', 'Pedidos')

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('admin.orders.index') }}" class="chip {{ $current === '' ? 'chip--on' : '' }}">Todos</a>
        @foreach ($statuses as $status)
            <a href="{{ route('admin.orders.index', ['estado' => $status->value]) }}"
               class="chip {{ $current === $status->value ? 'chip--on' : '' }}">{{ $status->label() }}</a>
        @endforeach
    </div>

    <div class="table-responsive" style="border:1px solid var(--line)">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="eyebrow">
                    <th>Pedido</th><th>Fecha</th><th>Cliente</th>
                    <th>Artículos</th><th>Estado</th><th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $order) }}" class="data">{{ $order->number }}</a></td>
                    <td class="data text-body-secondary">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-body-secondary">{{ $order->user?->name ?? $order->email }}</td>
                    <td class="data">{{ $order->items_count }}</td>
                    <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                    <td class="text-end price">{{ $order->total()->format() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-body-secondary">No hay pedidos con ese filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
