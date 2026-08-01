<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $products = $request->user()
            ? $request->user()->wishlistProducts()
                ->published()
                ->with(['team', 'primaryImage', 'variants'])
                ->withSum('variants as stock_total', 'stock')
                ->latest('wishlist_items.created_at')
                ->get()
            : collect();

        return view('shop.wishlist', ['products' => $products]);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $ids = collect($validated['ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $products = Product::published()
            ->whereIn('id', $ids)
            ->with(['team', 'primaryImage', 'variants'])
            ->withSum('variants as stock_total', 'stock')
            ->get()
            ->sortBy(fn (Product $product) => $ids->search($product->id))
            ->values();

        // Anotación explícita: tras sortBy()->values() Larastan pierde el
        // genérico de la colección y marca el closure de map() como
        // irresoluble.
        /** @var Collection<int, Product> $products */

        return response()->json([
            // Closure con tipo de retorno explícito: una arrow fn que
            // devuelve un array literal deja el tipo sin resolver para PHPStan.
            'data' => $products->map(function (Product $product): array {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'team' => $product->team->name,
                    'season' => $product->season,
                    'price' => $product->basePrice()->format(),
                    'url' => route('products.show', $product),
                    'image' => $product->primaryImage?->url() ?? asset('images/placeholder.svg'),
                    'stock' => (int) ($product->stock_total ?? 0),
                ];
            }),
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->is_active && $product->published_at?->isPast(), 404);

        $request->user()->wishlistProducts()->syncWithoutDetaching([$product->id]);

        return $this->state($request, 'Guardada en favoritos.');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->wishlistProducts()->detach($product->id);

        return $this->state($request, 'Eliminada de favoritos.');
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['nullable', 'array', 'max:50'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $ids = Product::published()
            ->whereIn('id', $validated['product_ids'] ?? [])
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            $request->user()->wishlistProducts()->syncWithoutDetaching($ids);
        }

        return $this->state($request);
    }

    private function state(Request $request, ?string $message = null): JsonResponse
    {
        $ids = $request->user()->wishlistProducts()
            ->published()
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return response()->json([
            'data' => [
                'ids' => $ids,
                'count' => $ids->count(),
                'message' => $message,
            ],
        ]);
    }
}
