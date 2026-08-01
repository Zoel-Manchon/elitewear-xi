<?php

namespace App\Console\Commands;

use App\Services\SportsData\RetroCatalogImporter;
use Illuminate\Console\Command;

final class ImportRetroCatalog extends Command
{
    protected $signature = 'catalog:retro-import
        {--team= : Importa solo el slug de un equipo}
        {--no-download : Crea productos sin descargar imágenes}
        {--force : Ejecuta sin confirmación}';

    protected $description = 'Añade el archivo de camisetas retro curadas desde Wikimedia Commons';

    public function handle(RetroCatalogImporter $importer): int
    {
        $shirts = collect((array) config('retro_catalog.shirts', []))
            ->when($this->option('team'), fn ($items, string $slug) => $items->where('team', $slug))
            ->values();

        if ($shirts->isEmpty()) {
            $this->error('No hay camisetas retro configuradas para ese filtro.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Se importarán {$shirts->count()} camisetas retro. ¿Continuar?", true)) {
            return self::SUCCESS;
        }

        $rows = [];
        $failures = 0;
        $totals = ['created' => 0, 'updated' => 0, 'downloaded' => 0];

        foreach ($shirts as $shirt) {
            $result = $importer->import($shirt, ! $this->option('no-download'));
            foreach ($totals as $key => $_) {
                $totals[$key] += $result[$key];
            }
            if ($result['status'] !== 'OK') {
                $failures++;
            }

            $rows[] = [
                $shirt['team'],
                $shirt['season'],
                $shirt['type'],
                $shirt['file'],
                $result['status'],
            ];
        }

        $this->table(['Equipo', 'Temporada', 'Tipo', 'Archivo Commons', 'Estado'], $rows);
        $this->newLine();
        $this->info("Nuevas: {$totals['created']} · Actualizadas: {$totals['updated']} · Imágenes: {$totals['downloaded']}");
        $this->line('TheSportsDB y el archivo retro conviven: este comando no desactiva las equipaciones actuales.');

        return ($totals['created'] + $totals['updated']) > 0 ? self::SUCCESS : self::FAILURE;
    }
}
