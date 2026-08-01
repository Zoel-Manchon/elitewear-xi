<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property string $number
 * @property OrderStatus $status
 * @property string $email
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $shipping_cents
 * @property int $total_cents
 * @property string $currency
 * @property array{full_name?: string|null, line1?: string|null, line2?: string|null, city?: string|null, province?: string|null, postal_code?: string|null, country_code?: string|null, phone?: string|null} $shipping_address
 * @property array{full_name?: string|null, line1?: string|null, line2?: string|null, city?: string|null, province?: string|null, postal_code?: string|null, country_code?: string|null, phone?: string|null}|null $billing_address
 * @property Carbon|null $placed_at
 * @property Carbon|null $paid_at
 * @property string|null $carrier
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property-read User|null $user
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Payment|null $latestPayment
 * @property-read Coupon|null $coupon
 */
class Order extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id', 'coupon_id', 'coupon_code', 'number', 'status', 'email',
        'subtotal_cents', 'discount_cents', 'shipping_cents', 'total_cents', 'currency',
        'shipping_address', 'billing_address', 'placed_at', 'paid_at',
        'carrier', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents, $this->currency);
    }

    public function subtotal(): Money
    {
        return Money::fromCents($this->subtotal_cents, $this->currency);
    }

    public function shipping(): Money
    {
        return Money::fromCents($this->shipping_cents, $this->currency);
    }

    public function discount(): Money
    {
        return Money::fromCents($this->discount_cents, $this->currency);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
