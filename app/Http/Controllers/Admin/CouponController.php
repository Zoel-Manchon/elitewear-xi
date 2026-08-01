<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['type' => CouponType::Percent])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('status', 'Código creado.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon));

        return back()->with('status', 'Código actualizado.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Código eliminado.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'integer', 'min:1'],
            'min_subtotal_eur' => ['nullable', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Un porcentaje mayor que 100 sería pagar al cliente por comprar.
        if ($data['type'] === CouponType::Percent->value && $data['value'] > 100) {
            $data['value'] = 100;
        }

        return [
            'code' => strtoupper($data['code']),
            'type' => $data['type'],
            'value' => $data['value'],
            'min_subtotal_cents' => (int) round(((float) ($data['min_subtotal_eur'] ?? 0)) * 100),
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
