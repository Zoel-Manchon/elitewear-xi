<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\SportsData\CatalogSportsDbSync;
use App\Services\SportsData\SportsDbCatalogProductReconciler;
use App\Services\SportsData\SportsDbOnlyCatalogCleaner;
use App\Services\SportsData\SportsDbTeamProfileMapper;
use App\Services\SportsData\TheSportsDbClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

final class BuildFullSportsDbCatalog extends Command
{
    protected $signature = 'catalog:sportsdb-full
        {--league=* : Descubre equipos de una o varias ligas de TheSportsDB}
        {--country=* : Descubre hasta 10 equipos de fútbol por cada país indicado}
        {--all-countries : Recorre todos los países que devuelve la clave actual}
        {--max-countries=0 : Limita los países de --all-countries; 0 significa todos}
        {--existing-only : No descubre equipos nuevos; usa únicamente los ya guardados}
        {--team= : Procesa un único slug local después del descubrimiento}
        {--no-download : Crea o actualiza productos sin descargar las imágenes}
        {--keep-stale : Conserva activos productos que no procedan del catálogo actual}
        {--keep-other-sources : No elimina imágenes ni referencias de proveedores anteriores}
        {--force : Ejecuta sin confirmación}';

    protected $description = 'Construye el mayor catálogo posible con los equipos, perfiles y equipaciones expuestos por TheSportsDB';

    public function handle(
        TheSportsDbClient $client,
        SportsDbTeamProfileMapper $profiles,
        CatalogSportsDbSync $sync,
        SportsDbCatalogProductReconciler $reconciler,
        SportsDbOnlyCatalogCleaner $cleaner,
    ): int {
        $this->applyKnownTeamIds();

        if (! $this->option('force')) {
            $this->warn('El catálogo visible se reconstruirá usando exclusivamente imágenes y datos de TheSportsDB.');

            if (! $this->confirm('¿Continuar?', false)) {
                $this->comment('Operación cancelada.');

                return self::SUCCESS;
            }
        }

        if (! $this->option('keep-other-sources')) {
            $cleaned = $cleaner->clean();
            $this->info(sprintf(
                'Limpieza: %d productos desactivados, %d imágenes eliminadas y %d referencias externas retiradas.',
                $cleaned['products_deactivated'],
                $cleaned['images_deleted'],
                $cleaned['equipment_deleted'],
            ));
        }

        /** @var array<int, array<string, mixed>> $knownProfiles */
        $knownProfiles = [];
        $discoveryRows = [];
        $discoveryFailures = 0;

        if (! $this->option('existing-only')) {
            try {
                $targets = $this->discoveryTargets($client);
            } catch (Throwable $exception) {
                $targets = collect();
                $discoveryFailures++;
                $discoveryRows[] = ['Países', 'all_countries.php', 0, 0, 0, $exception->getMessage()];
            }

            foreach ($targets as $target) {
                try {
                    $remoteTeams = $target['type'] === 'country'
                        ? $client->teamsByCountry($target['query'])
                        : $client->teamsByLeague($target['query']);
                    $created = 0;
                    $updated = 0;

                    foreach ($remoteTeams as $remoteTeam) {
                        $externalId = (int) ($remoteTeam['idTeam'] ?? 0);
                        if ($externalId <= 0) {
                            continue;
                        }

                        $existing = Team::query()->where('sportsdb_id', $externalId)->exists();
                        $team = $profiles->upsert($remoteTeam, $target['region']);
                        $knownProfiles[(int) $team->sportsdb_id] = $remoteTeam;
                        $existing ? $updated++ : $created++;
                    }

                    $discoveryRows[] = [
                        $target['type'] === 'country' ? 'País' : 'Liga',
                        $target['query'],
                        count($remoteTeams),
                        $created,
                        $updated,
                        'OK',
                    ];
                } catch (Throwable $exception) {
                    $discoveryFailures++;
                    $discoveryRows[] = [
                        $target['type'] === 'country' ? 'País' : 'Liga',
                        $target['query'],
                        0,
                        0,
                        0,
                        $exception->getMessage(),
                    ];
                }
            }

            $this->newLine();
            $this->info('Descubrimiento de equipos');
            $this->table(['Origen', 'Consulta', 'API', 'Nuevos', 'Actualizados', 'Estado'], $discoveryRows);

            if ($discoveryFailures > 0) {
                $this->warn("{$discoveryFailures} consultas de descubrimiento no pudieron resolverse; se continuará con el resto y con los equipos ya conocidos.");
            }
        }

        $teams = Team::query()
            ->whereNotNull('sportsdb_id')
            ->when($this->option('team'), fn ($query, string $slug) => $query->where('slug', $slug))
            ->orderBy('name')
            ->get();

        if ($teams->isEmpty()) {
            $this->error('No hay equipos disponibles para sincronizar.');

            return self::FAILURE;
        }

        $download = ! $this->option('no-download');
        $deactivateStale = ! $this->option('keep-stale');
        $rows = [];
        $failures = 0;

        $this->newLine();
        $this->info('Equipaciones y productos');

        foreach ($teams as $team) {
            try {
                $profile = $knownProfiles[(int) $team->sportsdb_id] ?? null;
                $result = $sync->syncKnownTeam($team, false, $profile);
                $stats = $reconciler->reconcile(
                    $result['team'],
                    $result['equipment'],
                    $download,
                    $deactivateStale,
                );

                $rows[] = [
                    $result['team']->name,
                    $result['team']->sportsdb_id,
                    $result['equipment']->count(),
                    $stats['created'],
                    $stats['updated'],
                    $stats['downloaded'],
                    $stats['deactivated'],
                    $stats['skipped'] === 0
                        ? 'OK'
                        // Mostrar el motivo, no solo el recuento: un
                        // "3 omitidas" obliga a ir al log; el mensaje
                        // resuelve el diagnóstico en el sitio.
                        : "{$stats['skipped']} omitidas · ".
                          Str::limit($stats['errors'][0] ?? 'sin detalle', 60),
                ];
            } catch (Throwable $exception) {
                $failures++;
                $rows[] = [
                    $team->name,
                    $team->sportsdb_id,
                    0,
                    0,
                    0,
                    0,
                    0,
                    $exception->getMessage(),
                ];
            }
        }

        $this->table(
            ['Equipo', 'ID', 'Equipaciones acumuladas', 'Nuevas', 'Actualizadas', 'Imágenes', 'OFF', 'Estado'],
            $rows,
        );

        $this->newLine();
        $this->info('Catálogo TheSportsDB actualizado.');
        $this->line('Se conservan todas las equipaciones de TheSportsDB vistas en sincronizaciones anteriores.');
        $this->line('También se aprovecha strEquipment del perfil cuando aporta una imagen distinta.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Collection<int, array{type: string, query: string, region: string}> */
    private function discoveryTargets(TheSportsDbClient $client): Collection
    {
        $countries = collect($this->option('country'))
            ->map(fn (mixed $country): string => trim((string) $country))
            ->filter()
            ->values();

        if ($this->option('all-countries')) {
            $countries = collect($client->countries());
            $limit = max(0, (int) $this->option('max-countries'));

            if ($limit > 0) {
                $countries = $countries->take($limit);
            }
        }

        if ($countries->isNotEmpty()) {
            return $countries->map(fn (string $country): array => [
                'type' => 'country',
                'query' => $country,
                'region' => '',
            ]);
        }

        $requested = collect($this->option('league'))
            ->map(fn (mixed $league): string => trim((string) $league))
            ->filter()
            ->values();

        if ($requested->isNotEmpty()) {
            return $requested->map(fn (string $query): array => [
                'type' => 'league',
                'query' => $query,
                'region' => '',
            ]);
        }

        /** @var array<int, array{query: string, region: string}> $configured */
        $configured = config('sportsdb_catalog.discovery_leagues', []);

        return collect($configured)->map(fn (array $league): array => [
            'type' => 'league',
            'query' => $league['query'],
            'region' => $league['region'],
        ]);
    }

    private function applyKnownTeamIds(): void
    {
        /** @var array<string, int> $map */
        $map = config('sportsdb_catalog.teams', []);

        foreach ($map as $slug => $id) {
            Team::query()->where('slug', $slug)->update(['sportsdb_id' => $id]);
        }
    }
}
