<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\SportsData\SportsDbKitImageImporter;
use Illuminate\Console\Command;

class ImportKitGalleries extends Command
{
    protected $signature = 'kits:gallery
        {--max=2 : Equipaciones a importar por producto}
        {--team= : Limitar a un equipo por slug}';

    protected $description = 'Completa la galería de cada producto con varias equipaciones de TheSportsDB';

    public function handle(SportsDbKitImageImporter $importer): int
    {
        $products = Product::query()
            ->with('team')
            ->whereHas('team', fn ($q) => $q->whereNotNull('sportsdb_id'))
            ->when($this->option('team'), fn ($q, $slug) => $q->whereRelation('team', 'slug', $slug))
            ->get();

        if ($products->isEmpty()) {
            $this->warn('No hay productos con equipo vinculado a TheSportsDB.');

            return self::SUCCESS;
        }

        $total = 0;
        $max = max(1, (int) $this->option('max'));

        $this->withProgressBar($products, function (Product $product) use ($importer, $max, &$total) {
            $total += $importer->importGallery($product, $max);
        });

        $this->newLine(2);
        $this->info("{$total} imágenes nuevas en {$products->count()} productos.");

        return self::SUCCESS;
    }
}
