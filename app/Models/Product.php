<?php

namespace App\Models;

use App\Enums\KitType;
use App\Enums\ShirtPattern;
use App\Support\Money;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $team_equipment_id
 * @property string $name
 * @property string $slug
 * @property string|null $season
 * @property KitType $kit_type
 * @property string|null $description
 * @property string $primary_color
 * @property string $secondary_color
 * @property ShirtPattern $pattern
 * @property int|null $shirt_number
 * @property int $base_price_cents
 * @property string $currency
 * @property bool $is_active
 * @property Carbon|null $published_at
 * @property-read Team $team
 * @property-read TeamEquipment|null $teamEquipment
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductImage> $images
 * @property-read ProductImage|null $primaryImage
 */
class Product extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    // $fillable explícito SIEMPRE. Nunca $guarded = [].
    protected $fillable = [
        'team_id', 'team_equipment_id', 'name', 'slug', 'season', 'kit_type', 'description',
        'primary_color', 'secondary_color', 'pattern', 'shirt_number',
        'base_price_cents', 'currency', 'is_active', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'kit_type' => KitType::class,
            'pattern' => ShirtPattern::class,
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'base_price_cents' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teamEquipment(): BelongsTo
    {
        return $this->belongsTo(TeamEquipment::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->published();
    }

    public function wishlistedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlist_items')->withTimestamps();
    }

    public function basePrice(): Money
    {
        return Money::fromCents($this->base_price_cents, $this->currency);
    }

    public function totalStock(): int
    {
        return (int) $this->variants()->sum('stock');
    }

    /** Solo lo publicado y activo: úsalo en TODAS las consultas de catálogo. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
