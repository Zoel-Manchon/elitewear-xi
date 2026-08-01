<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property int $min_subtotal_cents
 * @property int|null $max_redemptions
 * @property int $redemptions_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
class Coupon extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code', 'type', 'value', 'min_subtotal_cents',
        'max_redemptions', 'redemptions_count', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'min_subtotal_cents' => 'integer',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Motivo por el que NO se puede usar, o null si es válido.
     *
     * Devuelve un motivo genérico cuando el problema es el propio cupón
     * (caducado, agotado, inactivo): distinguirlos permitiría sondear qué
     * códigos existen probando cadenas al azar.
     */
    public function rejectionReason(int $subtotalCents): ?string
    {
        if (! $this->is_active
            || ($this->starts_at && $this->starts_at->isFuture())
            || ($this->expires_at && $this->expires_at->isPast())
            || ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions)
        ) {
            return 'Ese código no es válido.';
        }

        // Este sí es específico: el usuario puede actuar sobre él.
        if ($subtotalCents < $this->min_subtotal_cents) {
            $falta = number_format(($this->min_subtotal_cents - $subtotalCents) / 100, 2, ',', '.');

            return "Este código necesita un mínimo de compra. Te faltan {$falta} €.";
        }

        return null;
    }

    /** El descuento nunca puede superar el subtotal: un total negativo es un regalo. */
    public function discountFor(int $subtotalCents): int
    {
        $discount = $this->type === CouponType::Percent
            ? (int) floor($subtotalCents * $this->value / 100)
            : $this->value;

        return min($discount, $subtotalCents);
    }

    public function describe(): string
    {
        return $this->type === CouponType::Percent
            ? "{$this->value}% de descuento"
            : number_format($this->value / 100, 2, ',', '.').' € de descuento';
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
