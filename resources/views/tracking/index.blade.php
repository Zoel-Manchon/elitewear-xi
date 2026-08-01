@extends('layouts.app')

@section('title', 'Seguir un pedido')
@section('meta_description', 'Consulta el estado, el transportista y el localizador de tu pedido de Elitewear XI.')
@section('canonical', route('tracking.index'))
@section('robots', 'noindex,follow')

@section('content')
<div class="container py-5" style="max-width:680px">
    <p class="eyebrow mb-1">Seguimiento</p>
    <h1 class="display-6 mb-3">¿Dónde está tu pedido?</h1>
    <p class="text-body-secondary mb-4">
        Introduce el número de pedido y el correo utilizado durante la compra. No necesitas haber creado una cuenta.
    </p>

    <form method="POST" action="{{ route('tracking.lookup') }}" class="tracking-lookup p-4 p-md-5">
        @csrf
        <div class="mb-3">
            <label class="form-label eyebrow" for="number">Número de pedido</label>
            <input class="form-control form-control-lg data @error('number') is-invalid @enderror"
                   id="number" name="number" value="{{ old('number') }}" placeholder="RS-2026-000123" required>
            @error('number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label eyebrow" for="tracking_email">Correo electrónico</label>
            <input class="form-control form-control-lg @error('email') is-invalid @enderror"
                   type="email" id="tracking_email" name="email" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary btn-lg w-100">Consultar pedido</button>
    </form>
</div>
@endsection
