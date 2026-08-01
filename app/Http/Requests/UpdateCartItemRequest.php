<?php

namespace App\Http\Requests;

use App\Actions\Cart\AddItemToCart;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la propiedad de la línea se comprueba en el controlador
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0', 'max:'.AddItemToCart::MAX_PER_LINE],
        ];
    }
}
