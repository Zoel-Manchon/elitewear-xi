<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'full_name', 'line1', 'line2', 'city',
        'province', 'postal_code', 'country_code', 'phone', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Se copia tal cual dentro del pedido: snapshot inmutable. */
    public function toSnapshot(): array
    {
        return $this->only([
            'full_name', 'line1', 'line2', 'city',
            'province', 'postal_code', 'country_code', 'phone',
        ]);
    }
}
