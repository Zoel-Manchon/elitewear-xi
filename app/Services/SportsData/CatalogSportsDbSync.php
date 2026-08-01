<?php

namespace App\Services\SportsData;

use App\Models\Product;
use App\Models\Team;
use App\Models\TeamEquipment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CatalogSportsDbSync
{
    public function __construct(
        private readonly TheSportsDbClient $client,
        private readonly EquipmentMatcher $matcher,
        private readonly SportsDbTeamProfileMapper $profiles,
    ) {}

    /**
     * @return array{
     *     team: Team,
     *     equipment: Collection<int, TeamEquipment>,
     *     matched: int,
     *     profile: array<string, mixed>|null
     * }
     */
    public function syncTeam(Team $team, bool $matchProducts = true): array
    {
        $query = trim((string) ($team->sportsdb_query ?: $team->name));

        $remoteTeam = $team->sportsdb_id
            ? $this->client->lookupTeam((string) $team->sportsdb_id)
            : $this->client->searchTeam($query);

        if ($remoteTeam === null || empty($remoteTeam['idTeam'])) {
            $reference = $team->sportsdb_id
                ? "ID {$team->sportsdb_id}"
                : "«{$query}»";

            $team->update([
                'sportsdb_synced_at' => now(),
                'sportsdb_sync_error' => "No se encontró un equipo para {$reference}.",
            ]);

            return [
                'team' => $team->refresh(),
                'equipment' => collect(),
                'matched' => 0,
                'profile' => null,
            ];
        }

        $mapped = $this->profiles->upsert(
            $remoteTeam,
            $team->sportsdb_region,
        );

        // Conserva el nombre editorial local de los equipos ya existentes.
        if ($mapped->id === $team->id && $mapped->name !== $team->name) {
            $mapped->update(['name' => $team->name]);
        }

        return $this->syncKnownTeam(
            $mapped->refresh(),
            $matchProducts,
            $remoteTeam,
        );
    }

    /**
     * Sincroniza equipaciones mediante idTeam y enriquece el equipo con el
     * perfil remoto.
     *
     * La respuesta incluye todas las equipaciones de TheSportsDB acumuladas
     * para el equipo, no únicamente las devueltas por la petición actual.
     *
     * @param  array<string, mixed>|null  $remoteTeam
     * @return array{
     *     team: Team,
     *     equipment: Collection<int, TeamEquipment>,
     *     matched: int,
     *     profile: array<string, mixed>|null
     * }
     */
    public function syncKnownTeam(
        Team $team,
        bool $matchProducts = true,
        ?array $remoteTeam = null,
    ): array {
        if (! $team->sportsdb_id) {
            throw new \RuntimeException(
                "{$team->name} no tiene sportsdb_id configurado.",
            );
        }

        $profilePayload = $remoteTeam;

        try {
            $remoteTeam ??= $this->client->lookupTeam(
                (string) $team->sportsdb_id,
            );

            if ($remoteTeam !== null) {
                $profilePayload = $remoteTeam;
                $localName = $team->name;

                $team = $this->profiles->upsert(
                    $remoteTeam,
                    $team->sportsdb_region,
                );

                // No reemplaza nombres o traducciones editoriales existentes.
                if ($localName !== '' && $team->name !== $localName) {
                    $team->update(['name' => $localName]);
                }
            }

            /*
             * En el descubrimiento por liga o país, el perfil puede haber sido
             * guardado por SportsDbTeamProfileMapper antes de entrar aquí.
             *
             * La instancia $team recibida puede estar obsoleta y conservar
             * sportsdb_profile_payload = null. Por eso se vuelve a consultar
             * la fila antes de usar el perfil persistido como respaldo.
             */
            if ($profilePayload === null) {
                $team->refresh();

                $profilePayload = $this->normaliseProfilePayload(
                    $team->getAttribute('sportsdb_profile_payload'),
                );
            }

            $remoteEquipment = $this->client->equipment(
                (string) $team->sportsdb_id,
            );

            $this->persistEquipment(
                $team,
                $remoteEquipment,
                $profilePayload,
            );

            $team->update([
                'sportsdb_name' => $team->sportsdb_name
                    ?: $team->sportsdb_query
                    ?: $team->name,
                'sportsdb_synced_at' => now(),
                'sportsdb_sync_error' => null,
            ]);
        } catch (Throwable $exception) {
            $team->update([
                'sportsdb_synced_at' => now(),
                'sportsdb_sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $freshTeam = $team->refresh();

        $equipment = $freshTeam->equipment()
            ->where('provider', 'thesportsdb')
            ->get();

        $matched = $matchProducts
            ? $this->matchProducts($freshTeam, $equipment)
            : 0;

        return [
            'team' => $freshTeam,
            // collect() convierte Eloquent\Collection en Support\Collection,
            // que es lo que declara el tipo de retorno del método.
            'equipment' => collect($equipment),
            'matched' => $matched,
            'profile' => $profilePayload,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $remoteEquipment
     * @param  array<string, mixed>|null  $remoteTeam
     */
    private function persistEquipment(
        Team $team,
        array $remoteEquipment,
        ?array $remoteTeam,
    ): void {
        DB::transaction(function () use (
            $team,
            $remoteEquipment,
            $remoteTeam,
        ): void {
            /** @var array<int, string> $seenImageUrls */
            $seenImageUrls = [];

            foreach ($remoteEquipment as $item) {
                if (
                    empty($item['idEquipment'])
                    || empty($item['strEquipment'])
                ) {
                    continue;
                }

                $externalTeamId = trim((string) ($item['idTeam'] ?? ''));
                $currentExternalTeamId = (string) $team->sportsdb_id;

                // lookupequipment.php identifica también al propietario de la
                // equipación. Nunca permitimos que una respuesta destinada a
                // otro idTeam reasigne una TeamEquipment ya almacenada.
                //
                // Esta validación protege tanto frente a respuestas remotas
                // incoherentes como frente a fakes genéricos que devuelven la
                // misma equipación al sincronizar varios equipos.
                if (
                    $externalTeamId !== ''
                    && $externalTeamId !== $currentExternalTeamId
                ) {
                    continue;
                }

                $imageUrl = trim((string) $item['strEquipment']);

                if (! $this->client->isAllowedImageUrl($imageUrl)) {
                    continue;
                }

                $seenImageUrls[] = $imageUrl;

                $this->persistEquipmentItem(
                    $team,
                    $item,
                    (string) $item['idEquipment'],
                );
            }

            /*
             * lookupteam.php y search_all_teams.php pueden exponer otra imagen
             * mediante strEquipment. Se almacena como referencia sintética
             * cuando no coincide con ninguna imagen de lookupequipment.php.
             */
            $profileImage = trim(
                (string) ($remoteTeam['strEquipment'] ?? ''),
            );

            if (
                $profileImage === ''
                || ! $this->client->isAllowedImageUrl($profileImage)
                || in_array($profileImage, $seenImageUrls, true)
            ) {
                return;
            }

            $syntheticId = 'profile-'.substr(
                sha1($team->sportsdb_id.'|'.$profileImage),
                0,
                20,
            );

            $payload = [
                'idEquipment' => $syntheticId,
                'idTeam' => (string) $team->sportsdb_id,
                'date' => null,
                'strSeason' => null,
                'strEquipment' => $profileImage,
                'strType' => '1st',
                'strUsername' => null,
                '_source_endpoint' => 'lookupteam.php/search_all_teams.php',
                '_profile_equipment' => true,
            ];

            $this->persistEquipmentItem(
                $team,
                $payload,
                $syntheticId,
            );
        });
    }

    /**
     * Acepta tanto el cast array de Eloquent como un JSON almacenado sin cast.
     *
     * @return array<string, mixed>|null
     */
    private function normaliseProfilePayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        try {
            $decoded = json_decode(
                $payload,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! is_array($decoded)) {
                return null;
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function persistEquipmentItem(
        Team $team,
        array $item,
        string $externalId,
    ): TeamEquipment {
        $sourceDate = null;

        if (! empty($item['date'])) {
            try {
                $sourceDate = CarbonImmutable::parse(
                    (string) $item['date'],
                );
            } catch (Throwable) {
                $sourceDate = null;
            }
        }

        return TeamEquipment::updateOrCreate(
            [
                'provider' => 'thesportsdb',
                'external_equipment_id' => $externalId,
            ],
            [
                'team_id' => $team->id,
                'external_team_id' => (string) (
                    $item['idTeam'] ?? $team->sportsdb_id
                ),
                'source_created_at' => $sourceDate,
                'season' => isset($item['strSeason'])
                    && trim((string) $item['strSeason']) !== ''
                        ? trim((string) $item['strSeason'])
                        : null,
                'image_url' => trim((string) $item['strEquipment']),
                'equipment_type' => isset($item['strType'])
                    ? trim((string) $item['strType'])
                    : null,
                'contributor' => isset($item['strUsername'])
                    && trim((string) $item['strUsername']) !== ''
                        ? trim((string) $item['strUsername'])
                        : null,
                'raw_payload' => $item,
            ],
        );
    }

    /**
     * @param  Collection<int, TeamEquipment>|null  $equipment
     */
    public function matchProducts(
        Team $team,
        ?Collection $equipment = null,
    ): int {
        $equipment ??= $team->equipment()
            ->where('provider', 'thesportsdb')
            ->get();

        $matched = 0;

        Product::query()
            ->where('team_id', $team->id)
            ->get()
            ->each(function (Product $product) use (
                $equipment,
                &$matched,
            ): void {
                $match = $this->matcher->bestFor(
                    $product,
                    $equipment,
                );

                if (
                    $match
                    && $product->team_equipment_id !== $match->id
                ) {
                    $product->update([
                        'team_equipment_id' => $match->id,
                    ]);

                    $matched++;
                }
            });

        return $matched;
    }
}
