<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:160'],
            'line2' => ['nullable', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'save_address' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['postal_code.regex' => 'El código postal debe tener 5 dígitos.'];
    }

    public function address(): array
    {
        return $this->only([
            'full_name', 'line1', 'line2', 'city',
            'province', 'postal_code', 'country_code', 'phone',
        ]);
    }
}
