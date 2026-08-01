<?php

namespace App\Models;

use App\Enums\ShirtSize;
use App\Support\Money;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property ShirtSize $size
 * @property string $sku
 * @property int $price_delta_cents
 * @property int $stock
 * @property bool $is_active
 * @property-read Product $product
 */
class ProductVariant extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'product_id', 'size', 'sku', 'price_delta_cents', 'stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'size' => ShirtSize::class,
            'price_delta_cents' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Precio real de esta talla. Fuente única de verdad para carrito y checkout. */
    public function price(): Money
    {
        return Money::fromCents(
            $this->product->base_price_cents + $this->price_delta_cents,
            $this->product->currency,
        );
    }

    public function priceCents(): int
    {
        return $this->product->base_price_cents + $this->price_delta_cents;
    }

    public function auditLabel(): string
    {
        return $this->product->name.' · talla '.$this->size->value;
    }

    public function isAvailable(int $quantity = 1): bool
    {
        return $this->is_active && $this->stock >= $quantity;
    }
}
