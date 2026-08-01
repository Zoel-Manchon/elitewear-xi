<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\SportsData\CatalogSportsDbSync;
use App\Services\SportsData\SportsDbCatalogProductReconciler;
use Illuminate\Console\Command;
use Throwable;

class RebuildSportsDbCatalog extends Command
{
    protected $signature = 'catalog:sportsdb-rebuild
        {--team= : Slug de un único equipo}
        {--no-download : Crea productos y deja el placeholder sin descargar imágenes}
        {--keep-stale : Conserva activos los productos que no aparecen en la respuesta actual}
        {--delay= : Pausa en milisegundos entre equipos}
        {--force : Ejecuta sin confirmación}';

    protected $description = 'Reconstruye el catálogo visible con todas las equipaciones devueltas por TheSportsDB';

    public function handle(
        CatalogSportsDbSync $sync,
        SportsDbCatalogProductReconciler $reconciler,
    ): int {
        $this->applyKnownTeamIds();

        $teams = Team::query()
            ->whereNotNull('sportsdb_id')
            ->when($this->option('team'), fn ($query, string $slug) => $query->where('slug', $slug))
            ->orderBy('name')
            ->get();

        if ($teams->isEmpty()) {
            $this->error('No hay equipos configurados para ese filtro.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Se desactivarán las camisetas antiguas que no aparezcan en la respuesta actual de TheSportsDB.');

            if (! $this->confirm('¿Reconstruir el catálogo visible?', false)) {
                $this->comment('Operación cancelada.');

                return self::SUCCESS;
            }
        }

        $download = ! $this->option('no-download');
        $deactivateStale = ! $this->option('keep-stale');
        $delayMs = $this->resolveDelay($teams->count());
        $failures = 0;
        $rows = [];

        foreach ($teams as $index => $team) {
            try {
                // Con IDs conocidos evitamos searchteams.php y lookupteam.php:
                // una única petición gratuita por equipo.
                $result = $sync->syncKnownTeam($team, false);
                $stats = $reconciler->reconcile(
                    $result['team'],
                    $result['equipment'],
                    $download,
                    $deactivateStale,
                );

                $rows[] = [
                    $team->name,
                    $team->sportsdb_id,
                    $result['equipment']->count(),
                    $stats['created'],
                    $stats['updated'],
                    $stats['downloaded'],
                    $stats['deactivated'],
                    $stats['skipped'] === 0 ? 'OK' : "{$stats['skipped']} omitidas",
                ];
            } catch (Throwable $exception) {
                $failures++;
                $team->update([
                    'sportsdb_synced_at' => now(),
                    'sportsdb_sync_error' => $exception->getMessage(),
                ]);

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

            if ($delayMs > 0 && $index < $teams->count() - 1) {
                usleep($delayMs * 1000);
            }
        }

        $this->table(
            ['Equipo', 'ID', 'API', 'Nuevas', 'Actualizadas', 'Imágenes', 'Antiguas OFF', 'Estado'],
            $rows,
        );

        $this->newLine();
        $this->info('El catálogo visible queda limitado a las equipaciones realmente devueltas por la API.');
        $this->line('Las camisetas antiguas se conservan en la base de datos como inactivas para no romper pedidos históricos.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function applyKnownTeamIds(): void
    {
        /** @var array<string, int> $map */
        $map = config('sportsdb_catalog.teams', []);

        foreach ($map as $slug => $id) {
            Team::query()->where('slug', $slug)->update(['sportsdb_id' => $id]);
        }
    }

    private function resolveDelay(int $teamCount): int
    {
        if ($this->option('delay') !== null) {
            return max(0, (int) $this->option('delay'));
        }

        $freeKey = trim((string) config('services.thesportsdb.api_key')) === '123';

        return $freeKey && $teamCount > 1
            ? (int) config('sportsdb_catalog.free_delay_ms', 2100)
            : 0;
    }
}
