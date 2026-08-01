<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const SORTS = [
        'newest' => ['published_at', 'desc'],
        'price_asc' => ['base_price_cents', 'asc'],
        'price_desc' => ['base_price_cents', 'desc'],
        'name' => ['name', 'asc'],
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'team' => ['nullable', 'string', 'exists:teams,slug'],
            'season' => ['nullable', 'string', 'max:9'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'gte:min_price'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        [$column, $direction] = self::SORTS[$filters['sort'] ?? 'newest'];

        $products = Product::query()
            ->published()
            ->with(['team', 'primaryImage', 'teamEquipment'])
            ->when($filters['team'] ?? null, fn ($q, $slug) => $q->whereRelation('team', 'slug', $slug))
            ->when($filters['season'] ?? null, fn ($q, $season) => $q->where('season', $season))
            ->when($filters['min_price'] ?? null, fn ($q, $min) => $q->where('base_price_cents', '>=', $min))
            ->when($filters['max_price'] ?? null, fn ($q, $max) => $q->where('base_price_cents', '<=', $max))
            ->orderBy($column, $direction)
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_active && $product->published_at?->isPast(), 404);

        return new ProductResource(
            $product->load(['team', 'teamEquipment', 'variants', 'images'])
        );
    }
}
