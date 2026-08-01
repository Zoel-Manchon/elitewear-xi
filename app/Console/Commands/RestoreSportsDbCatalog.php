<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\SportsData\CatalogSportsDbSync;
use App\Services\SportsData\SportsDbCatalogProductReconciler;
use Illuminate\Console\Command;
use Throwable;

final class RestoreSportsDbCatalog extends Command
{
    protected $signature = 'catalog:sportsdb-restore
        {--team= : Procesa solo un slug local}
        {--no-download : Conserva las URLs y productos sin descargar imágenes}
        {--force : Ejecuta sin confirmación}';

    protected $description = 'Restaura las equipaciones disponibles de TheSportsDB sin eliminar el catálogo existente';

    public function handle(
        CatalogSportsDbSync $sync,
        SportsDbCatalogProductReconciler $reconciler,
    ): int {
        $this->applyKnownIds();

        if (! $this->option('force')
            && ! $this->confirm('Se añadirán o actualizarán las equipaciones de TheSportsDB. ¿Continuar?', true)) {
            return self::SUCCESS;
        }

        $teams = Team::query()
            ->whereNotNull('sportsdb_id')
            ->when($this->option('team'), fn ($query, string $slug) => $query->where('slug', $slug))
            ->orderBy('name')
            ->get();

        if ($teams->isEmpty()) {
            $this->error('No hay equipos con sportsdb_id para sincronizar.');

            return self::FAILURE;
        }

        $rows = [];
        $failures = 0;
        $download = ! $this->option('no-download');

        foreach ($teams as $team) {
            try {
                $result = $sync->syncKnownTeam($team, false);
                $stats = $reconciler->reconcile(
                    $result['team'],
                    $result['equipment'],
                    downloadImages: $download,
                    // Restaurar nunca debe desactivar productos ya existentes.
                    deactivateStale: false,
                );

                $rows[] = [
                    $result['team']->name,
                    $result['team']->sportsdb_id,
                    $result['equipment']->count(),
                    $stats['created'],
                    $stats['updated'],
                    $stats['downloaded'],
                    $stats['skipped'] === 0 ? 'OK' : "{$stats['skipped']} omitidas",
                ];
            } catch (Throwable $exception) {
                $failures++;
                $rows[] = [$team->name, $team->sportsdb_id, 0, 0, 0, 0, $exception->getMessage()];
            }
        }

        $this->table(
            ['Equipo', 'ID', 'Equipaciones acumuladas', 'Nuevas', 'Actualizadas', 'Imágenes', 'Estado'],
            $rows,
        );

        $this->newLine();
        $this->info('Restauración TheSportsDB finalizada sin eliminar productos existentes.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function applyKnownIds(): void
    {
        /** @var array<string, int> $map */
        $map = config('sportsdb_catalog.teams', []);

        foreach ($map as $slug => $id) {
            Team::query()->where('slug', $slug)->update(['sportsdb_id' => $id]);
        }
    }
}
