<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    // is_approved y published_at NO son asignables en masa: deciden si una
    // reseña se publica, y el controlador crea la reseña con datos del
    // request. Con ellos aquí, un POST con is_approved=1 se autoaprueba.
    // Se establecen explícitamente con forceFill() o en la moderación.
    protected $fillable = [
        'user_id', 'product_id', 'order_item_id', 'rating', 'title', 'body',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_approved', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
