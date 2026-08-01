<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'provider', 'provider_order_id', 'provider_capture_id',
        'status', 'amount_cents', 'currency', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount_cents' => 'integer',
        ];
    }

    // El payload crudo del proveedor no se expone nunca por la API.
    protected $hidden = ['payload'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents, $this->currency);
    }
}
