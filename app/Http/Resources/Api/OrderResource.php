<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'totals' => [
                'subtotal_cents' => $this->subtotal_cents,
                'shipping_cents' => $this->shipping_cents,
                'total_cents' => $this->total_cents,
                'currency' => $this->currency,
                'formatted' => $this->total()->format(),
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'sku' => $item->sku,
                'name' => $item->product_name,
                'team' => $item->team_name,
                'season' => $item->season,
                'size' => $item->size,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'line_total_cents' => $item->line_total_cents,
            ])),
        ];
    }
}
