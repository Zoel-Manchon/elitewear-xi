<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Team;
use App\Models\TeamEquipment;
use App\Services\SportsData\CatalogSportsDbSync;
use App\Services\SportsData\SportsDbKitImageImporter;
use Illuminate\Console\Command;
use Throwable;

class SyncSportsDbCatalog extends Command
{
    protected $signature = 'catalog:sportsdb-sync
        {--team= : Slug o nombre de un único equipo}
        {--sportsdb-id= : ID remoto; requiere seleccionar un único equipo}
        {--download : Descarga la imagen cuando hay coincidencia exacta}
        {--replace-primary : Convierte la imagen importada en principal}
        {--no-match : Guarda datos sin vincularlos a productos}';

    protected $description = 'Modo legado: vincula TheSportsDB con productos ya existentes';

    public function handle(CatalogSportsDbSync $sync, SportsDbKitImageImporter $importer): int
    {
        $teams = Team::query()
            ->when($this->option('team'), function ($query, string $team): void {
                $query->where(fn ($nested) => $nested
                    ->where('slug', $team)
                    ->orWhere('name', 'like', '%'.addcslashes($team, '%_\\').'%'));
            })
            ->orderBy('name')
            ->get();

        if ($teams->isEmpty()) {
            $this->error('No se encontró ningún equipo con ese filtro.');

            return self::FAILURE;
        }

        if ($this->option('sportsdb-id')) {
            if (! $this->option('team') || $teams->count() !== 1) {
                $this->error('--sportsdb-id requiere --team y debe resolver un único equipo.');

                return self::FAILURE;
            }

            $teams->first()->update(['sportsdb_id' => (int) $this->option('sportsdb-id')]);
        }

        $failures = 0;
        $rows = [];

        foreach ($teams as $team) {
            try {
                $result = $sync->syncTeam($team, ! $this->option('no-match'));
                $downloaded = 0;

                if ($this->option('download')) {
                    Product::query()
                        ->where('team_id', $team->id)
                        ->with('teamEquipment')
                        ->get()
                        ->each(function (Product $product) use ($importer, &$downloaded): void {
                            /** @var TeamEquipment|null $equipment */
                            $equipment = $product->getRelation('teamEquipment');

                            if (! $equipment) {
                                return;
                            }

                            $importer->import(
                                $product,
                                $equipment,
                                (bool) $this->option('replace-primary'),
                            );
                            $downloaded++;
                        });
                }

                $rows[] = [
                    $team->name,
                    $result['team']->sportsdb_name ?: '—',
                    $result['equipment']->count(),
                    $result['matched'],
                    $downloaded,
                    'OK',
                ];
            } catch (Throwable $exception) {
                $failures++;
                $team->update([
                    'sportsdb_synced_at' => now(),
                    'sportsdb_sync_error' => $exception->getMessage(),
                ]);

                $rows[] = [$team->name, '—', 0, 0, 0, $exception->getMessage()];
            }
        }

        $this->table(
            ['Equipo local', 'TheSportsDB', 'Equipaciones', 'Vinculadas', 'Imágenes', 'Estado'],
            $rows,
        );

        $this->newLine();
        $this->line('Este comando conserva el catálogo existente. Para sustituirlo por las equipaciones devueltas por la API usa:');
        $this->info('php artisan catalog:sportsdb-full --force');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
