@extends('layouts.admin')

@section('eyebrow', 'Panel')
@section('title', 'Resumen')

@section('content')
    <div class="row g-3 mb-5">
        @foreach ([
            ['Ingresos este mes', $revenueThisMonth->format(), ''],
            ['Pedidos pendientes', $ordersPending, $ordersPending > 0 ? 'color:var(--gold)' : ''],
            ['Pedidos hoy', $ordersToday, ''],
            ['Productos publicados', $productsPublished, ''],
        ] as [$label, $value, $style])
            <div class="col-6 col-xl-3">
                <div class="stat-card h-100">
                    <p class="eyebrow mb-2">{{ $label }}</p>
                    <p class="stat-card__value mb-0" style="{{ $style }}">{{ $value }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <p class="eyebrow mb-3">Stock bajo</p>
            <div class="table-responsive" style="border:1px solid var(--line)">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                    @forelse ($lowStock as $variant)
                        <tr>
                            <td>
                                <a href="{{ route('admin.products.edit', $variant->product) }}">
                                    {{ $variant->product->name }}
                                </a>
                                <span class="data text-body-secondary">· {{ $variant->size->value }}</span>
                            </td>
                            <td class="text-end">
                                <span class="badge {{ $variant->stock === 0 ? 'bg-danger' : 'bg-warning text-dark' }}">
                                    {{ $variant->stock }} uds.
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-4 text-body-secondary">Todo con stock suficiente.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-xl-6">
            <p class="eyebrow mb-3">Últimos pedidos</p>
            <div class="table-responsive" style="border:1px solid var(--line)">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $order) }}" class="data">{{ $order->number }}</a></td>
                            <td class="text-body-secondary small">{{ $order->user?->name ?? $order->email }}</td>
                            <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td class="text-end data">{{ $order->total()->format() }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-4 text-body-secondary">Todavía no hay pedidos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
