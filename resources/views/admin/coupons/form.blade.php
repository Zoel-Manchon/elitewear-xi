@extends('layouts.admin')

@section('eyebrow', 'Promociones')
@section('title', $coupon->exists ? 'Editar código' : 'Nuevo código')

@section('content')
<form method="POST"
      action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
      style="max-width:640px">
    @csrf
    @if ($coupon->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label eyebrow" for="code">Código</label>
            <input type="text" class="form-control data text-uppercase" id="code" name="code"
                   value="{{ old('code', $coupon->code) }}" required maxlength="32"
                   placeholder="XI10">
            <p class="search-hint mt-1 mb-0">Letras, números, guion y guion bajo</p>
        </div>

        <div class="col-md-3">
            <label class="form-label eyebrow" for="type">Tipo</label>
            <select class="form-select" id="type" name="type">
                @foreach (\App\Enums\CouponType::cases() as $type)
                    <option value="{{ $type->value }}"
                        @selected(old('type', $coupon->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label eyebrow" for="value">Valor</label>
            <input type="number" class="form-control" id="value" name="value" min="1"
                   value="{{ old('value', $coupon->value) }}" required>
            <p class="search-hint mt-1 mb-0">% o céntimos</p>
        </div>

        <div class="col-md-6">
            <label class="form-label eyebrow" for="min_subtotal_eur">Compra mínima (€)</label>
            <input type="number" step="0.01" min="0" class="form-control"
                   id="min_subtotal_eur" name="min_subtotal_eur"
                   value="{{ old('min_subtotal_eur', number_format($coupon->min_subtotal_cents / 100, 2, '.', '')) }}">
        </div>

        <div class="col-md-6">
            <label class="form-label eyebrow" for="max_redemptions">Usos máximos</label>
            <input type="number" min="1" class="form-control" id="max_redemptions"
                   name="max_redemptions" value="{{ old('max_redemptions', $coupon->max_redemptions) }}"
                   placeholder="Sin límite">
            @if ($coupon->exists)
                <p class="search-hint mt-1 mb-0">Canjeado {{ $coupon->redemptions_count }} veces</p>
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label eyebrow" for="starts_at">Desde</label>
            <input type="datetime-local" class="form-control" id="starts_at" name="starts_at"
                   value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}">
        </div>

        <div class="col-md-6">
            <label class="form-label eyebrow" for="expires_at">Hasta</label>
            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at"
                   value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d\TH:i')) }}">
        </div>

        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                       id="is_active" @checked(old('is_active', $coupon->exists ? $coupon->is_active : true))>
                <label class="form-check-label" for="is_active">Activo</label>
            </div>
        </div>

        <div class="col-12 d-flex gap-2 mt-3">
            <button class="btn btn-primary">{{ $coupon->exists ? 'Guardar' : 'Crear código' }}</button>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-light">Cancelar</a>
        </div>
    </div>
</form>
@endsection
