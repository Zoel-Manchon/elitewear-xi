<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'size' => $this->size->value,
            'price' => [
                'amount_cents' => $this->priceCents(),
                'formatted' => $this->price()->format(),
            ],
            // Disponibilidad, no stock exacto: el inventario es información
            // de negocio y no tiene por qué salir en una API pública.
            'in_stock' => $this->stock > 0,
        ];
    }
}
