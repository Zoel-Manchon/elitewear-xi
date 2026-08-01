@extends('layouts.admin')

@section('eyebrow', 'Catálogo')
@section('title', 'Productos')

@section('actions')
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Nuevo producto</a>
@endsection

@section('content')
    <form method="GET" class="mb-4" style="max-width:380px">
        <input type="search" name="q" value="{{ $search }}" class="form-control"
               placeholder="Buscar por nombre" aria-label="Buscar productos">
    </form>

    <div class="table-responsive" style="border:1px solid var(--line)">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="eyebrow">
                    <th colspan="2">Producto</th><th>Equipo</th><th>Temporada</th>
                    <th>Stock</th><th>Precio</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    <td style="width:56px">
                        <img src="{{ $product->primaryImage?->url() ?? asset('images/placeholder.svg') }}"
                             alt="" width="40" height="50" style="object-fit:cover">
                    </td>
                    <td><a href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a></td>
                    <td class="text-body-secondary">{{ $product->team->name }}</td>
                    <td class="data">{{ $product->season }}</td>
                    <td class="data {{ ($product->stock_total ?? 0) == 0 ? 'text-danger' : '' }}">
                        {{ $product->stock_total ?? 0 }}
                    </td>
                    <td class="data">{{ $product->basePrice()->format() }}</td>
                    <td>
                        @if ($product->is_active && $product->published_at)
                            <span class="badge bg-success">Publicado</span>
                        @else
                            <span class="badge bg-secondary">Borrador</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                              onsubmit="return confirm('¿Retirar {{ $product->name }} del catálogo?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-link text-body-secondary">Retirar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-4 text-body-secondary">No hay productos todavía.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
