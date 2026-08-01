@extends('layouts.admin')

@section('eyebrow', 'Promociones')
@section('title', 'Códigos de descuento')

@section('actions')
    <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">Nuevo código</a>
@endsection

@section('content')
    <div class="table-responsive" style="border:1px solid var(--line)">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="eyebrow">
                    <th>Código</th><th>Descuento</th><th>Mínimo</th>
                    <th>Usos</th><th>Vigencia</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($coupons as $coupon)
                <tr>
                    <td><a href="{{ route('admin.coupons.edit', $coupon) }}" class="data fw-bold">{{ $coupon->code }}</a></td>
                    <td class="data">{{ $coupon->describe() }}</td>
                    <td class="data text-body-secondary">
                        {{ $coupon->min_subtotal_cents ? number_format($coupon->min_subtotal_cents / 100, 2, ',', '.').' €' : '—' }}
                    </td>
                    <td class="data">
                        {{ $coupon->redemptions_count }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }}
                    </td>
                    <td class="data small text-body-secondary">
                        {{ $coupon->expires_at?->format('d/m/Y') ?? 'Sin caducidad' }}
                    </td>
                    <td>
                        @if ($coupon->is_active)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}"
                              onsubmit="return confirm('¿Eliminar el código {{ $coupon->code }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-link text-body-secondary">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-4 text-body-secondary">No hay códigos todavía.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $coupons->links() }}</div>
@endsection
