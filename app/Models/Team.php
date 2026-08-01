<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'country_code', 'crest_path',
        'sportsdb_id', 'sportsdb_query', 'sportsdb_name',
        'sportsdb_short_name', 'sportsdb_alternate_name', 'sportsdb_formed_year',
        'sportsdb_sport', 'sportsdb_league', 'sportsdb_league_id',
        'sportsdb_country', 'sportsdb_region', 'sportsdb_stadium',
        'sportsdb_stadium_capacity', 'sportsdb_location', 'sportsdb_keywords',
        'sportsdb_website', 'sportsdb_description_es', 'sportsdb_description_en',
        'sportsdb_badge_url', 'sportsdb_logo_url', 'sportsdb_banner_url',
        'sportsdb_fanart_url', 'sportsdb_fanart_urls', 'sportsdb_youtube',
        'banner_path', 'fanart_path', 'media_synced_at',
        'sportsdb_equipment_url',
        'sportsdb_colour_1', 'sportsdb_colour_2', 'sportsdb_colour_3',
        'sportsdb_profile_payload', 'sportsdb_synced_at', 'sportsdb_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'sportsdb_fanart_urls' => 'array',
            'media_synced_at' => 'datetime',
            'sportsdb_id' => 'integer',
            'sportsdb_formed_year' => 'integer',
            'sportsdb_stadium_capacity' => 'integer',
            'sportsdb_profile_payload' => 'array',
            'sportsdb_synced_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Sin esta anotación, PHPStan deduce Collection<int, Model> en vez de
     * Collection<int, TeamEquipment>: HasMany no propaga el tipo del modelo
     * relacionado por sí solo. De ahí venían los errores de tipo en
     * CatalogSportsDbSync.
     *
     * @return HasMany<TeamEquipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(TeamEquipment::class)
            ->where('provider', 'thesportsdb')
            ->orderByDesc('season');
    }

    public function sportsDbDescription(): ?string
    {
        return $this->sportsdb_description_es ?: $this->sportsdb_description_en;
    }

    public function sportsDbWebsiteUrl(): ?string
    {
        $website = trim((string) $this->sportsdb_website);

        if ($website === '') {
            return null;
        }

        if (! str_starts_with($website, 'http://') && ! str_starts_with($website, 'https://')) {
            $website = 'https://'.$website;
        }

        return filter_var($website, FILTER_VALIDATE_URL) ? $website : null;
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path
            ? Storage::disk('public')->url($this->banner_path)
            : null;
    }

    public function fanartUrl(): ?string
    {
        return $this->fanart_path
            ? Storage::disk('public')->url($this->fanart_path)
            : null;
    }

    /** Id del vídeo de YouTube, o null. Solo aceptamos ids con forma válida. */
    public function youtubeId(): ?string
    {
        if (! $this->sportsdb_youtube) {
            return null;
        }

        // La URL viene de un tercero: no se incrusta tal cual en un iframe.
        // Se extrae el id y se valida con el juego de caracteres de YouTube.
        preg_match('#(?:v=|youtu\\.be/|embed/)([A-Za-z0-9_-]{11})#', $this->sportsdb_youtube, $m);

        return $m[1] ?? null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
