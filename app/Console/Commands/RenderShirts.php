<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ShirtRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RenderShirts extends Command
{
    protected $signature = 'shirts:render {--force : Rehace las que ya existen}';

    protected $description = 'Genera las imágenes SVG de las camisetas a partir de sus colores';

    public function handle(ShirtRenderer $renderer): int
    {
        $products = Product::with('team')->get();
        $written = 0;

        $this->withProgressBar($products, function (Product $product) use ($renderer, &$written) {
            foreach ($renderer->render($product) as $view => $svg) {
                $path = "products/{$product->slug}-{$view}.svg";

                if (! $this->option('force') && Storage::disk('public')->exists($path)) {
                    continue;
                }

                Storage::disk('public')->put($path, $svg);
                $written++;
            }
        });

        $this->newLine(2);
        $this->info("{$written} imágenes generadas en storage/app/public/products.");

        return self::SUCCESS;
    }
}
