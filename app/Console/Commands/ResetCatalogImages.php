<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResetCatalogImages extends Command
{
    protected $signature = 'catalog:images-reset
        {--force : Ejecuta la limpieza sin pedir confirmación}
        {--keep-equipment-links : Conserva team_equipment_id en los productos}';

    protected $description = 'Elimina todas las imágenes del catálogo y deja los productos usando el placeholder global';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $imageCount = ProductImage::query()->count();
        $fileCount = count($disk->allFiles('products'));
        $productCount = Product::query()->count();
        $linkedCount = Product::query()->whereNotNull('team_equipment_id')->count();
        $clearLinks = ! $this->option('keep-equipment-links');

        if ($imageCount === 0 && $fileCount === 0 && (! $clearLinks || $linkedCount === 0)) {
            $this->info("El catálogo ya está limpio. Los {$productCount} productos usan el placeholder.");

            return self::SUCCESS;
        }

        $this->warn("Se eliminarán {$imageCount} registros de imagen y {$fileCount} archivos de storage/app/public/products.");

        if ($clearLinks) {
            $this->line("También se reiniciarán {$linkedCount} vínculos producto/equipación para volver a calcular coincidencias.");
        }

        $this->line('El placeholder global public/images/placeholder.svg no se eliminará.');

        if (! $this->option('force') && ! $this->confirm('¿Continuar con la limpieza completa?', false)) {
            $this->comment('Operación cancelada.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($clearLinks): void {
                ProductImage::query()->delete();

                if ($clearLinks) {
                    Product::query()->whereNotNull('team_equipment_id')->update([
                        'team_equipment_id' => null,
                    ]);
                }
            });

            // Borra también archivos huérfanos que no tuviesen registro en la BD.
            $disk->deleteDirectory('products');
            $disk->makeDirectory('products/imported');
        } catch (Throwable $exception) {
            $this->error('No se pudo completar la limpieza: '.$exception->getMessage());

            return self::FAILURE;
        }

        $remainingImages = ProductImage::query()->count();
        $remainingFiles = count($disk->allFiles('products'));

        $this->newLine();
        $this->info('Catálogo restablecido correctamente.');
        $this->table(
            ['Productos', 'Registros eliminados', 'Archivos eliminados', 'Registros restantes', 'Archivos restantes', 'Imagen visible'],
            [[
                $productCount,
                $imageCount,
                $fileCount,
                $remainingImages,
                $remainingFiles,
                'public/images/placeholder.svg',
            ]],
        );

        $this->newLine();
        $this->line('La siguiente imagen importada para cada producto será principal automáticamente.');
        $this->line('TheSportsDB completo: php artisan catalog:sportsdb-full --force');

        return self::SUCCESS;
    }
}
