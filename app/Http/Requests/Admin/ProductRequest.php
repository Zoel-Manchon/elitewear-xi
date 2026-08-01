<?php

namespace App\Http\Requests\Admin;

use App\Enums\KitType;
use App\Enums\ShirtSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'team_id' => ['required', 'exists:teams,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('products', 'slug')->ignore($productId)],
            'season' => ['nullable', 'string', 'max:20'],
            'kit_type' => ['required', Rule::enum(KitType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_price_eur' => ['required', 'numeric', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],

            'variants' => ['required', 'array'],
            'variants.*.size' => ['required', Rule::enum(ShirtSize::class)],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:9999'],
            'variants.*.price_delta_eur' => ['nullable', 'numeric', 'min:-100', 'max:100'],

            // Validación real del fichero: extensión, tipo MIME y dimensiones.
            // 'image' de Laravel ya rechaza SVG por defecto (vector = XSS).
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=400,min_height=400'],
        ];
    }

    public function priceCents(): int
    {
        return (int) round($this->float('base_price_eur') * 100);
    }
}
