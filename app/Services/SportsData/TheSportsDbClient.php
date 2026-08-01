<?php

namespace App\Services\SportsData;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TheSportsDbClient
{
    private float $lastRequestAt = 0.0;

    /** @return array<string, mixed>|null */
    public function searchTeam(string $query): ?array
    {
        $teams = $this->get('searchteams.php', ['t' => $query])->json('teams');

        if (! is_array($teams) || $teams === []) {
            return null;
        }

        $soccer = collect($teams)->first(
            fn (mixed $team): bool => is_array($team)
                && strtolower((string) ($team['strSport'] ?? '')) === 'soccer'
        );

        return is_array($soccer) ? $soccer : (is_array($teams[0] ?? null) ? $teams[0] : null);
    }

    /** @return array<string, mixed>|null */
    public function lookupTeam(string|int $teamId): ?array
    {
        $team = $this->get('lookupteam.php', ['id' => (string) $teamId])->json('teams.0');

        return is_array($team) ? $team : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function teamsByLeague(string $league): array
    {
        $teams = $this->get('search_all_teams.php', ['l' => $league])->json('teams');

        if (! is_array($teams)) {
            return [];
        }

        return array_values(array_filter($teams, fn (mixed $team): bool => is_array($team)
            && strtolower((string) ($team['strSport'] ?? '')) === 'soccer'));
    }

    /** @return array<int, array<string, mixed>> */
    public function teamsByCountry(string $country): array
    {
        $teams = $this->get('search_all_teams.php', [
            's' => 'Soccer',
            'c' => $country,
        ])->json('teams');

        if (! is_array($teams)) {
            return [];
        }

        return array_values(array_filter($teams, fn (mixed $team): bool => is_array($team)
            && strtolower((string) ($team['strSport'] ?? '')) === 'soccer'));
    }

    /** @return array<int, string> */
    public function countries(): array
    {
        $countries = $this->get('all_countries.php')->json('countries');

        if (! is_array($countries)) {
            return [];
        }

        return collect($countries)
            // Collection::filter() pasa (valor, clave) al callback: 'is_array'
            // como string recibiría dos argumentos y PHP 8 lanza TypeError.
            ->filter(fn (mixed $country): bool => is_array($country))
            ->map(fn (array $country): string => trim((string) ($country['name_en'] ?? '')))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function equipment(string|int $teamId): array
    {
        $equipment = $this->get('lookupequipment.php', ['id' => (string) $teamId])->json('equipment');

        if (! is_array($equipment)) {
            return [];
        }

        return array_values(array_filter($equipment, 'is_array'));
    }

    /** @return array{body: string, extension: string, content_type: string} */
    public function download(string $url): array
    {
        $this->assertAllowedImageUrl($url);

        $response = Http::accept('*/*')
            ->withUserAgent(config('app.name').'/SportsDB image importer')
            ->timeout((int) config('services.thesportsdb.timeout', 15))
            ->retry(2, 350, throw: false)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("No se pudo descargar la imagen ({$response->status()}).");
        }

        $body = $response->body();
        $maxBytes = (int) config('services.thesportsdb.max_image_bytes', 8_388_608);

        if ($body === '' || strlen($body) > $maxBytes) {
            throw new RuntimeException('La imagen remota está vacía o supera el tamaño permitido.');
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];

        if (! isset($extensions[$contentType])) {
            throw new RuntimeException("Tipo de imagen no permitido: {$contentType}");
        }

        return [
            'body' => $body,
            'extension' => $extensions[$contentType],
            'content_type' => $contentType,
        ];
    }

    public function isAllowedImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && in_array($host, ['www.thesportsdb.com', 'thesportsdb.com', 'r2.thesportsdb.com'], true);
    }

    private function assertAllowedImageUrl(string $url): void
    {
        if (! $this->isAllowedImageUrl($url)) {
            throw new RuntimeException('La URL de imagen no pertenece a un dominio permitido de TheSportsDB.');
        }
    }

    /** @param array<string, scalar> $query */
    private function get(string $endpoint, array $query = []): Response
    {
        $this->throttleFreeTier();

        return $this->request()->get($endpoint, $query)->throw();
    }

    private function throttleFreeTier(): void
    {
        if (trim((string) config('services.thesportsdb.api_key')) !== '123') {
            return;
        }

        if (function_exists('app') && app()->bound('env') && app()->environment('testing')) {
            return;
        }

        $intervalMs = max(0, (int) config('sportsdb_catalog.free_request_interval_ms', 2100));
        if ($intervalMs === 0 || $this->lastRequestAt <= 0) {
            $this->lastRequestAt = microtime(true);

            return;
        }

        $elapsedMs = (microtime(true) - $this->lastRequestAt) * 1000;
        $remainingMs = $intervalMs - $elapsedMs;

        if ($remainingMs > 0) {
            usleep((int) ceil($remainingMs * 1000));
        }

        $this->lastRequestAt = microtime(true);
    }

    private function request(): PendingRequest
    {
        $apiKey = trim((string) config('services.thesportsdb.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('Configura THESPORTSDB_API_KEY en el archivo .env.');
        }

        $baseUrl = rtrim((string) config('services.thesportsdb.base_url'), '/');

        return Http::baseUrl("{$baseUrl}/{$apiKey}")
            ->acceptJson()
            ->withUserAgent(config('app.name').'/SportsDB catalog sync')
            ->timeout((int) config('services.thesportsdb.timeout', 15))
            ->retry(2, 350, throw: false);
    }
}
