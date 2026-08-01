<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $token
 * @property Carbon|null $expires_at
 * @property-read User|null $user
 * @property-read Collection<int, CartItem> $items
 */
class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'token', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function subtotal(): Money
    {
        return $this->items
            ->reduce(
                fn (Money $carry, CartItem $item): Money => $carry->plus($item->lineTotal()),
                Money::zero(),
            );
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
