<?php

namespace App\Models;

use App\Enums\KitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $provider
 * @property string $external_equipment_id
 * @property string $external_team_id
 * @property Carbon|null $source_created_at
 * @property string|null $season
 * @property string $image_url
 * @property string|null $equipment_type
 * @property string|null $contributor
 * @property array<string, mixed>|null $raw_payload
 */
class TeamEquipment extends Model
{
    protected $table = 'team_equipments';

    protected $fillable = [
        'team_id',
        'provider',
        'external_equipment_id',
        'external_team_id',
        'source_created_at',
        'season',
        'image_url',
        'equipment_type',
        'contributor',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'source_created_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function kitType(): ?KitType
    {
        $type = strtolower(trim((string) $this->equipment_type));

        return match ($type) {
            '1st', 'first', 'home', 'local' => KitType::Home,
            '2nd', 'second', 'away', 'visitante' => KitType::Away,
            '3rd', 'third', 'tercera' => KitType::Third,
            'gk', 'goalkeeper', 'keeper', 'portero' => KitType::Goalkeeper,
            '', 'unknown', 'n/a' => null,
            default => KitType::Other,
        };
    }

    public function typeLabel(): string
    {
        return $this->kitType()?->label() ?? ($this->equipment_type ?: 'Sin clasificar');
    }
}
