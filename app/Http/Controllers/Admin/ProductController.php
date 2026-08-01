<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SaveProductImages;
use App\Enums\KitType;
use App\Enums\ShirtSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        return view('admin.products.index', [
            'products' => Product::query()
                ->with(['team', 'primaryImage'])
                ->withSum('variants as stock_total', 'stock')
                ->when($search, fn ($query, $term) => $query->where(
                    'name', 'like', '%'.addcslashes($term, '%_\\').'%'
                ))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(['kit_type' => KitType::Home, 'is_active' => true]),
            'teams' => Team::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'sizes' => ShirtSize::cases(),
            'variants' => collect(),
            'selectedCategories' => [],
        ]);
    }

    public function store(ProductRequest $request, SaveProductImages $images): RedirectResponse
    {
        $product = Product::create($this->attributes($request));

        $this->syncVariants($product, $request->input('variants', []));
        $product->categories()->sync($request->input('categories', []));

        if ($request->hasFile('images')) {
            $images->handle($product, $request->file('images'));
        }

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Producto creado.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load(['images', 'team.equipment', 'teamEquipment']),
            'teams' => Team::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'sizes' => ShirtSize::cases(),
            'variants' => $product->variants->keyBy(fn ($v) => $v->size->value),
            'selectedCategories' => $product->categories->pluck('id')->all(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, SaveProductImages $images): RedirectResponse
    {
        $product->update($this->attributes($request, $product));

        $this->syncVariants($product, $request->input('variants', []));
        $product->categories()->sync($request->input('categories', []));

        if ($request->hasFile('images')) {
            $images->handle($product, $request->file('images'));
        }

        return back()->with('status', 'Producto actualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete: los pedidos históricos conservan su snapshot igualmente,
        // pero así no perdemos el catálogo por un clic.
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Producto retirado del catálogo.');
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        // Si borramos la principal, ascendemos la siguiente.
        if ($image->is_primary) {
            $product->images()->orderBy('position')->first()?->update(['is_primary' => true]);
        }

        return back()->with('status', 'Imagen eliminada.');
    }

    private function attributes(ProductRequest $request, ?Product $product = null): array
    {
        $publishedAt = $request->boolean('published')
            ? ($product->published_at ?? now())
            : null;

        return [
            'team_id' => $request->integer('team_id'),
            'name' => $request->string('name')->toString(),
            'slug' => $request->filled('slug')
                ? Str::slug($request->string('slug')->toString())
                : Str::slug($request->string('name')->toString()),
            'season' => $request->string('season')->toString() ?: null,
            'kit_type' => $request->string('kit_type')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'base_price_cents' => $request->priceCents(),
            'currency' => 'EUR',
            'is_active' => $request->boolean('is_active'),
            'published_at' => $publishedAt,
        ];
    }

    private function syncVariants(Product $product, array $variants): void
    {
        foreach ($variants as $data) {
            $size = $data['size'];

            $product->variants()->updateOrCreate(
                ['size' => $size],
                [
                    'sku' => $product->variants()->where('size', $size)->value('sku')
                        ?? strtoupper(Str::slug($product->slug).'-'.$size),
                    'stock' => (int) $data['stock'],
                    'price_delta_cents' => (int) round((float) ($data['price_delta_eur'] ?? 0) * 100),
                    'is_active' => true,
                ],
            );
        }
    }
}
