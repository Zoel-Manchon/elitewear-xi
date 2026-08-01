@extends('layouts.app')

@section('title', 'Checkout')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="container py-4 py-lg-5">
    <p class="eyebrow mb-1">Paso 1 de 2</p>
    <h1 class="h2 mb-4">Dónde te lo enviamos</h1>

    @error('stock')
        <div class="alert alert-warning">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('checkout.store') }}">
        @csrf

        <div class="row g-4 g-lg-5">
            <div class="col-lg-7">
                <div class="row g-3">
                    @php
                        $fields = [
                            ['full_name', 'Nombre y apellidos', 'text', 12, $address?->full_name],
                            ['email', 'Correo electrónico', 'email', 12, $checkoutEmail],
                            ['line1', 'Dirección', 'text', 12, $address?->line1],
                            ['line2', 'Piso, puerta, escalera (opcional)', 'text', 12, $address?->line2],
                            ['postal_code', 'Código postal', 'text', 4, $address?->postal_code],
                            ['city', 'Localidad', 'text', 8, $address?->city],
                            ['province', 'Provincia', 'text', 6, $address?->province],
                            ['phone', 'Teléfono (opcional)', 'tel', 6, $address?->phone],
                        ];
                    @endphp

                    @foreach ($fields as [$name, $label, $type, $cols, $value])
                        <div class="col-md-{{ $cols }}">
                            <label class="form-label eyebrow" for="{{ $name }}">{{ $label }}</label>
                            <input type="{{ $type }}" class="form-control @error($name) is-invalid @enderror"
                                   id="{{ $name }}" name="{{ $name }}"
                                   value="{{ old($name, $value) }}"
                                   @required(! str_contains($label, 'opcional'))>
                            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach

                    <input type="hidden" name="country_code" value="ES">

                    @auth
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="save_address"
                                       value="1" id="save_address" checked>
                                <label class="form-check-label" for="save_address">Guardar esta dirección para la próxima vez</label>
                            </div>
                        </div>
                    @else
                        <div class="col-12">
                            <div class="guest-checkout-note">
                                <strong>Compra como invitado</strong>
                                <span>No necesitas registrarte. Recibirás por correo un enlace firmado para seguir el pedido.</span>
                            </div>
                        </div>
                    @endauth
                </div>
            </div>

            <div class="col-lg-5">
                <div class="p-4" style="border:1px solid var(--line);background:var(--pitch-900)">
                    <p class="eyebrow mb-3">Tu pedido</p>

                    @foreach ($cart->items as $item)
                        <div class="d-flex justify-content-between align-items-start py-2 small">
                            <span>
                                {{ $item->variant->product->name }}<br>
                                <span class="text-body-secondary data">
                                    Talla {{ $item->variant->size->value }} × {{ $item->quantity }}
                                </span>
                            </span>
                            <span class="data">{{ $item->lineTotal()->format() }}</span>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-between py-2 mt-2" style="border-top:1px solid var(--line)">
                        <span class="text-body-secondary">Subtotal</span>
                        <span class="data">{{ $cart->subtotal()->format() }}</span>
                    </div>

                    @if ($coupon)
                        <div class="d-flex justify-content-between py-2 align-items-center">
                            <span class="d-flex align-items-center gap-2">
                                <span class="chip chip--on" style="cursor:default">{{ $coupon->code }}</span>
                                <button form="quitar-cupon" class="btn btn-link btn-sm p-0 text-body-secondary">
                                    quitar
                                </button>
                            </span>
                            <span class="data" style="color:var(--grass)">
                                −{{ number_format($discountCents / 100, 2, ',', '.') }} €
                            </span>
                        </div>
                    @else
                        <div class="py-2">
                            <label class="visually-hidden" for="coupon_code">Código de descuento</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control text-uppercase data" id="coupon_code"
                                       name="code" form="aplicar-cupon" placeholder="Código de descuento"
                                       maxlength="32">
                                <button class="btn btn-outline-light" form="aplicar-cupon">Aplicar</button>
                            </div>
                            @error('code')
                                <p class="small text-danger mb-0 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="d-flex justify-content-between py-2">
                        <span class="text-body-secondary">Envío</span>
                        <span class="data">
                            {{ $shippingCents === 0 ? 'Gratis' : number_format($shippingCents / 100, 2, ',', '.').' €' }}
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-baseline py-2"
                         style="border-top:1px solid var(--line)">
                        <span class="eyebrow">Total</span>
                        <span class="price fs-3">
                            {{ number_format(($cart->subtotal()->cents - $discountCents + $shippingCents) / 100, 2, ',', '.') }} €
                        </span>
                    </div>

                    <button class="btn btn-primary btn-lg w-100 mt-3">Continuar al pago</button>

                    <p class="search-hint mt-3 mb-0">
                        El importe se calcula en el servidor a partir de tu carrito.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Fuera del formulario principal: un <form> dentro de otro no es válido --}}
<form id="aplicar-cupon" method="POST" action="{{ route('coupons.store') }}">@csrf</form>
<form id="quitar-cupon" method="POST" action="{{ route('coupons.destroy') }}">@csrf @method('DELETE')</form>
@endsection
