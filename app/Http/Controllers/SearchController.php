<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        // Laravel bindea el valor, así que no hay inyección. Escapamos % y _
        // porque son comodines de LIKE: sin esto, "?q=%" devuelve el catálogo
        // entero y convierte el buscador en un volcado de la tabla.
        $term = '%'.addcslashes($validated['q'], '%_\\').'%';

        $products = Product::query()
            ->published()
            ->with(['team', 'primaryImage'])
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('season', 'like', $term)
                    ->orWhereRelation('team', 'name', 'like', $term)
                    ->orWhereRelation('team', 'country_code', 'like', $term);
            })
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$term])
            ->limit(8)
            ->get();

        // Anotación explícita: sin ella Larastan no resuelve el genérico
        // de la colección y marca el closure de map() como irresoluble.
        /** @var Collection<int, Product> $products */
        $products = collect($products);

        return response()->json([
            // Closure con tipo de retorno explícito: una arrow fn que
            // devuelve un array literal deja el tipo sin resolver para PHPStan.
            'results' => $products->map(function (Product $product): array {
                return [
                    'name' => $product->name,
                    'team' => $product->team->name,
                    'season' => $product->season,
                    'price' => $product->basePrice()->format(),
                    'image' => $product->primaryImage?->url() ?? asset('images/placeholder.svg'),
                    'url' => route('products.show', $product),
                ];
            })->values(),
        ]);
    }
}
