<?php

namespace App\Services\SportsData;

use App\Models\Team;
use Illuminate\Support\Str;

final class SportsDbTeamProfileMapper
{
    /** @param array<string, mixed> $remote */
    public function upsert(array $remote, ?string $forcedRegion = null): Team
    {
        $id = isset($remote['idTeam']) ? (int) $remote['idTeam'] : 0;

        if ($id <= 0) {
            throw new \InvalidArgumentException('El perfil remoto no contiene un idTeam válido.');
        }

        $name = trim((string) ($remote['strTeam'] ?? ''));
        if ($name === '') {
            $name = "TheSportsDB {$id}";
        }

        $team = Team::query()->where('sportsdb_id', $id)->first();
        $slug = $team?->slug ?: $this->uniqueSlug($name, $id);
        $country = $this->clean($remote['strCountry'] ?? null);

        $attributes = [
            'name' => $team?->name ?: $name,
            'slug' => $slug,
            'country_code' => $team?->country_code ?: $this->countryCode($country),
            'sportsdb_id' => $id,
            'sportsdb_query' => $team?->sportsdb_query ?: $name,
            'sportsdb_name' => $name,
            'sportsdb_short_name' => $this->clean($remote['strTeamShort'] ?? null),
            'sportsdb_alternate_name' => $this->clean($remote['strTeamAlternate'] ?? null),
            'sportsdb_formed_year' => $this->positiveInt($remote['intFormedYear'] ?? null),
            'sportsdb_sport' => $this->clean($remote['strSport'] ?? null),
            'sportsdb_league' => $this->clean($remote['strLeague'] ?? null),
            'sportsdb_league_id' => $this->clean($remote['idLeague'] ?? null),
            'sportsdb_country' => $country,
            'sportsdb_region' => $forcedRegion ?: $this->regionForCountry($country),
            'sportsdb_stadium' => $this->clean($remote['strStadium'] ?? null),
            'sportsdb_stadium_capacity' => $this->positiveInt($remote['intStadiumCapacity'] ?? null),
            'sportsdb_location' => $this->clean($remote['strLocation'] ?? null),
            'sportsdb_keywords' => $this->clean($remote['strKeywords'] ?? null),
            'sportsdb_website' => $this->clean($remote['strWebsite'] ?? null),
            'sportsdb_description_es' => $this->clean($remote['strDescriptionES'] ?? null),
            'sportsdb_description_en' => $this->clean($remote['strDescriptionEN'] ?? null),
            'sportsdb_badge_url' => $this->clean($remote['strBadge'] ?? null),
            'sportsdb_logo_url' => $this->clean($remote['strLogo'] ?? null),
            'sportsdb_banner_url' => $this->clean($remote['strBanner'] ?? null),
            'sportsdb_fanart_url' => $this->clean($remote['strFanart1'] ?? null),
            // La API expone hasta cuatro fanart por equipo; antes solo se
            // guardaba el primero y los otros tres se perdían.
            'sportsdb_fanart_urls' => array_values(array_filter([
                $this->clean($remote['strFanart2'] ?? null),
                $this->clean($remote['strFanart3'] ?? null),
                $this->clean($remote['strFanart4'] ?? null),
            ])),
            'sportsdb_youtube' => $this->clean($remote['strYoutube'] ?? null),
            'sportsdb_equipment_url' => $this->clean($remote['strEquipment'] ?? null),
            'sportsdb_colour_1' => $this->normaliseColour($remote['strColour1'] ?? null),
            'sportsdb_colour_2' => $this->normaliseColour($remote['strColour2'] ?? null),
            'sportsdb_colour_3' => $this->normaliseColour($remote['strColour3'] ?? null),
            'sportsdb_profile_payload' => $remote,
            'sportsdb_synced_at' => now(),
            'sportsdb_sync_error' => null,
        ];

        if ($team) {
            $team->update($attributes);

            return $team->fresh();
        }

        return Team::query()->create($attributes);
    }

    private function uniqueSlug(string $name, int $id): string
    {
        $base = Str::slug($name) ?: "team-{$id}";
        $slug = $base;
        $suffix = 2;

        while (Team::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    private function normaliseColour(mixed $value): ?string
    {
        $colour = $this->clean($value);

        if ($colour === null) {
            return null;
        }

        if (preg_match('/^#?[0-9a-f]{6}$/i', $colour) !== 1) {
            return null;
        }

        return '#'.ltrim(strtoupper($colour), '#');
    }

    private function regionForCountry(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $america = ['Argentina', 'Bolivia', 'Brazil', 'Canada', 'Chile', 'Colombia', 'Costa Rica', 'Ecuador', 'Mexico', 'Paraguay', 'Peru', 'United States', 'Uruguay', 'Venezuela'];
        $asia = ['Australia', 'China', 'India', 'Iran', 'Iraq', 'Japan', 'Qatar', 'Saudi Arabia', 'South Korea', 'Thailand', 'United Arab Emirates'];
        $africa = ['Algeria', 'Cameroon', 'DR Congo', 'Egypt', 'Ghana', 'Ivory Coast', 'Morocco', 'Nigeria', 'Senegal', 'South Africa', 'Tunisia'];

        return match (true) {
            in_array($country, $america, true) => 'america',
            in_array($country, $asia, true) => 'asia',
            in_array($country, $africa, true) => 'africa',
            default => 'europa',
        };
    }

    private function countryCode(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        return [
            'Argentina' => 'AR', 'Australia' => 'AU', 'Austria' => 'AT', 'Belgium' => 'BE',
            'Brazil' => 'BR', 'Cameroon' => 'CM', 'Canada' => 'CA', 'Chile' => 'CL',
            'China' => 'CN', 'Colombia' => 'CO', 'Croatia' => 'HR', 'Denmark' => 'DK',
            'Egypt' => 'EG', 'England' => 'GB', 'France' => 'FR', 'Germany' => 'DE',
            'Greece' => 'GR', 'Iran' => 'IR', 'Italy' => 'IT', 'Japan' => 'JP',
            'Mexico' => 'MX', 'Morocco' => 'MA', 'Netherlands' => 'NL', 'Nigeria' => 'NG',
            'Portugal' => 'PT', 'Saudi Arabia' => 'SA', 'Scotland' => 'GB', 'South Africa' => 'ZA',
            'South Korea' => 'KR', 'Spain' => 'ES', 'Switzerland' => 'CH', 'Turkey' => 'TR',
            'United States' => 'US', 'Uruguay' => 'UY',
        ][$country] ?? null;
    }
}
