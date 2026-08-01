<?php

namespace App\Http\Requests;

use App\Actions\Cart\AddItemToCart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // cualquiera puede tener carrito, también los invitados
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => [
                'required',
                'integer',
                // La variante debe existir Y estar activa: si no, se puede
                // colar en el carrito una talla retirada del catálogo.
                Rule::exists('product_variants', 'id')->where('is_active', true),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.AddItemToCart::MAX_PER_LINE],
        ];
    }

    public function messages(): array
    {
        return [
            'product_variant_id.required' => 'Elige una talla.',
            'product_variant_id.exists' => 'Esa talla ya no está disponible.',
        ];
    }
}
