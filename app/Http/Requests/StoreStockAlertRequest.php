<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where('is_active', true),
            ],
            // La comprobación DNS rechaza dominios inexistentes y reduce el
            // uso del formulario como pasarela hacia buzones falsos, pero
            // hace una consulta de red real: en los tests fallaría siempre
            // (example.com no tiene registro MX) y ataría la suite a
            // tener conectividad.
            // La comprobación DNS rechaza dominios inexistentes, pero hace una
            // consulta de red real: en los tests fallaría siempre (example.com
            // no tiene registro MX) y ataría la suite a tener conectividad.
            //
            // Se decide por configuración y NO por app()->runningUnitTests():
            // ese helper depende de que APP_ENV valga "testing", y en Docker
            // env_file inyecta el .env con APP_ENV=local. Es exactamente el
            // mismo no-op silencioso que nos costó tres intentos con el CSRF.
            'email' => [
                'required',
                config('app.validate_email_dns', true) ? 'email:rfc,dns' : 'email:rfc',
                'max:180',
            ],
        ];
    }
}
