<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockAlert extends Model
{
    use HasFactory;

    // notified_at DEBE estar aquí: el observador lo marca con update() y,
    // sin ser asignable en masa, Eloquent lo descarta en silencio.
    protected $fillable = ['product_variant_id', 'user_id', 'email', 'token', 'notified_at'];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $alert) {
            $alert->token ??= (string) Str::uuid();
        });
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('notified_at');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
