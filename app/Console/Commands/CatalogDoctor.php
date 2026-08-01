<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Console\Command;

/**
 * Diagnóstico del catálogo.
 *
 * "El catálogo sale vacío" puede ser que no haya productos, que los haya pero
 * sin publicar, o que estén publicados pero sin variantes ni imágenes. Son
 * tres problemas distintos con tres arreglos distintos, y desde la interfaz
 * los tres se ven igual.
 */
class CatalogDoctor extends Command
{
    protected $signature = 'catalog:doctor {--publish : Publica los productos que solo estén sin publicar}';

    protected $description = 'Explica por qué el catálogo aparece vacío';

    public function handle(): int
    {
        $total = Product::count();
        $publicados = Product::published()->count();
        $inactivos = Product::where('is_active', false)->count();
        $sinFecha = Product::whereNull('published_at')->count();
        $futuros = Product::whereNotNull('published_at')
            ->where('published_at', '>', now())->count();
        $conVariantes = Product::has('variants')->count();
        $conImagen = Product::has('images')->count();

        $this->table(['Métrica', 'Valor'], [
            ['Equipos', Team::count()],
            ['Categorías', Category::count()],
            ['Productos (total)', $total],
            ['  → visibles en la tienda', $publicados],
            ['  → inactivos (is_active = 0)', $inactivos],
            ['  → sin published_at', $sinFecha],
            ['  → con published_at futuro', $futuros],
            ['  → con al menos una talla', $conVariantes],
            ['  → con al menos una imagen', $conImagen],
            ['Variantes con stock', ProductVariant::where('stock', '>', 0)->count()],
            ['Imágenes', ProductImage::count()],
        ]);

        if ($total === 0) {
            $this->error('No hay ni un producto. La siembra no llegó a ejecutarse.');
            $this->line('  php artisan db:seed --class=CatalogSeeder');

            return self::FAILURE;
        }

        if ($publicados === 0) {
            $this->warn('Hay productos, pero ninguno cumple published(): is_active = 1 y published_at <= ahora.');

            if ($this->option('publish')) {
                $n = Product::query()
                    ->where(fn ($q) => $q->where('is_active', false)->orWhereNull('published_at'))
                    ->update(['is_active' => true, 'published_at' => now()]);

                $this->info("{$n} productos publicados.");

                return self::SUCCESS;
            }

            $this->line('  Publícalos con: php artisan catalog:doctor --publish');

            return self::FAILURE;
        }

        if ($conVariantes < $publicados) {
            $this->warn('Hay productos publicados sin ninguna talla: no se pueden comprar.');
        }

        if ($conImagen < $publicados) {
            $this->warn('Hay productos publicados sin imagen: saldrán con el marcador de posición.');
            $this->line('  php artisan kits:gallery --max=2');
        }

        $this->info("Catálogo correcto: {$publicados} productos visibles.");

        return self::SUCCESS;
    }
}
