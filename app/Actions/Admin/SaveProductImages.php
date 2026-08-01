<?php

namespace App\Actions\Admin;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Interfaces\ImageManagerInterface;

class SaveProductImages
{
    public function __construct(private readonly ImageManagerInterface $images) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Product $product, array $files): void
    {
        $position = (int) $product->images()->max('position');

        foreach ($files as $file) {
            // El nombre lo generamos nosotros, nunca el del cliente: un
            // getClientOriginalName() sin sanear es path traversal servido.
            $name = Str::slug($product->slug).'-'.Str::random(8).'.webp';
            $path = "products/{$name}";

            // Reencodear elimina metadatos y cualquier payload escondido
            // detrás de una cabecera de imagen válida.
            $encoded = $this->images
                ->decode($file)
                ->scaleDown(width: 1400)
                ->encodeUsingFormat(Format::WEBP, quality: 82, strip: true);

            Storage::disk('public')->put($path, $encoded->toString());

            $product->images()->create([
                'path' => $path,
                'alt' => $product->name,
                'position' => ++$position,
                'is_primary' => $product->images()->count() === 0,
            ]);
        }
    }
}
