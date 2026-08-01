<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    /**
     * Whitelist de ordenaciones. NUNCA pases input del usuario a orderBy():
     * el nombre de columna no se bindea como parámetro, se interpola.
     */
    private const SORTS = [
        'novedades' => ['published_at', 'desc'],
        'precio-asc' => ['base_price_cents', 'asc'],
        'precio-desc' => ['base_price_cents', 'desc'],
        'nombre' => ['name', 'asc'],
    ];

    private const SORT_LABELS = [
        'novedades' => 'Novedades',
        'precio-asc' => 'Precio: de menor a mayor',
        'precio-desc' => 'Precio: de mayor a menor',
        'nombre' => 'Nombre',
    ];

    private const TYPE_LABELS = [
        'clubes' => 'Clubes',
        'selecciones' => 'Selecciones',
    ];

    private const REGION_LABELS = [
        'europa' => 'Europa',
        'america' => 'América',
        'asia' => 'Asia',
        'africa' => 'África',
    ];

    /** Respaldo cuando la extensión PHP intl no está disponible. */
    private const COUNTRY_LABELS = [
        'AR' => 'Argentina', 'AU' => 'Australia', 'AT' => 'Austria', 'BE' => 'Bélgica',
        'BO' => 'Bolivia', 'BR' => 'Brasil', 'CM' => 'Camerún', 'CA' => 'Canadá',
        'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica',
        'HR' => 'Croacia', 'CD' => 'República Democrática del Congo', 'DK' => 'Dinamarca',
        'EC' => 'Ecuador', 'EG' => 'Egipto', 'GB' => 'Reino Unido', 'FR' => 'Francia',
        'DE' => 'Alemania', 'GH' => 'Ghana', 'GR' => 'Grecia', 'IN' => 'India',
        'IR' => 'Irán', 'IQ' => 'Irak', 'IT' => 'Italia', 'CI' => 'Costa de Marfil',
        'JP' => 'Japón', 'KR' => 'Corea del Sur', 'MA' => 'Marruecos', 'MX' => 'México',
        'NL' => 'Países Bajos', 'NG' => 'Nigeria', 'PY' => 'Paraguay', 'PE' => 'Perú',
        'PT' => 'Portugal', 'QA' => 'Catar', 'RS' => 'Serbia', 'SA' => 'Arabia Saudí',
        'SN' => 'Senegal', 'ZA' => 'Sudáfrica', 'ES' => 'España', 'CH' => 'Suiza',
        'TH' => 'Tailandia', 'TN' => 'Túnez', 'TR' => 'Turquía', 'AE' => 'Emiratos Árabes Unidos',
        'US' => 'Estados Unidos', 'UY' => 'Uruguay', 'VE' => 'Venezuela',
    ];

    public function index(Request $request): View
    {
        $this->normaliseFilters($request);

        $filters = $request->validate([
            'equipo' => ['nullable', 'string', 'exists:teams,slug'],
            'tipo' => ['sometimes', 'array', 'max:2'],
            'tipo.*' => ['string', 'distinct', Rule::in(array_keys(self::TYPE_LABELS))],
            'region' => ['sometimes', 'array', 'max:4'],
            'region.*' => ['string', 'distinct', Rule::in(array_keys(self::REGION_LABELS))],
            'pais' => ['sometimes', 'array', 'max:40'],
            'pais.*' => ['string', 'size:2', 'distinct', Rule::exists('teams', 'country_code')],
            'q' => ['nullable', 'string', 'max:80'],
            'orden' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'precio_min' => ['nullable', 'numeric', 'min:0'],
            // Sin 'gte:precio_min': esa regla falla cuando el campo con el
            // que compara no viene en la petición, y ?precio_max=100 a secas
            // es un caso perfectamente normal. El orden se corrige abajo.
            'precio_max' => ['nullable', 'numeric', 'min:0'],
        ]);

        [$column, $direction] = self::SORTS[$filters['orden'] ?? 'novedades'];

        $products = $this->filteredProducts($filters)
            ->with(['team', 'primaryImage'])
            ->withSum('variants as stock_total', 'stock')
            ->orderBy($column, $direction)
            ->paginate(12)
            ->withQueryString();

        $filteredTeam = isset($filters['equipo'])
            ? Team::query()->where('slug', $filters['equipo'])->first()
            : null;

        $typeOptions = $this->categoryOptions(self::TYPE_LABELS);
        $regionOptions = $this->categoryOptions(self::REGION_LABELS);
        $countryOptions = $this->countryOptions();

        return view('shop.index', [
            'products' => $products,
            'filteredTeam' => $filteredTeam,
            'sorts' => self::SORT_LABELS,
            'filters' => $filters,
            'typeOptions' => $typeOptions,
            'regionOptions' => $regionOptions,
            'countryOptions' => $countryOptions,
            'activeFilters' => $this->activeFilterPills(
                $filters,
                $countryOptions,
                $filteredTeam,
            ),
        ]);
    }

    /**
     * Acepta tanto las URLs antiguas (`tipo=clubes`) como las nuevas facetas
     * multiselección (`tipo[]=clubes&tipo[]=selecciones`).
     */
    private function normaliseFilters(Request $request): void
    {
        if ($request->filled('categoria') && ! $request->has('tipo') && ! $request->has('region')) {
            $legacy = $request->string('categoria')->toString();

            if (array_key_exists($legacy, self::TYPE_LABELS)) {
                $request->merge(['tipo' => [$legacy]]);
            } elseif (array_key_exists($legacy, self::REGION_LABELS)) {
                $request->merge(['region' => [$legacy]]);
            }
        }

        foreach (['tipo', 'region', 'pais'] as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $values = collect(Arr::wrap($request->input($key)))
                ->filter(fn (mixed $value): bool => is_scalar($value))
                ->map(fn (mixed $value): string => trim((string) $value))
                ->filter()
                ->when($key === 'pais', fn ($items) => $items->map(fn (string $value) => strtoupper($value)))
                ->unique()
                ->values()
                ->all();

            $request->merge([$key => $values]);
        }
    }

    /** @param array<string, mixed> $filters */
    private function filteredProducts(array $filters): Builder
    {
        // Si llegan invertidos (el slider puede mandarlos así al arrastrar
        // un tirador por encima del otro), se intercambian en vez de
        // rechazar la petición.
        if (isset($filters['precio_min'], $filters['precio_max'])
            && (float) $filters['precio_min'] > (float) $filters['precio_max']) {
            [$filters['precio_min'], $filters['precio_max']] =
                [$filters['precio_max'], $filters['precio_min']];
        }

        $priceMinCents = isset($filters['precio_min'])
            ? (int) round((float) $filters['precio_min'] * 100)
            : null;
        $priceMaxCents = isset($filters['precio_max'])
            ? (int) round((float) $filters['precio_max'] * 100)
            : null;

        return Product::query()
            ->published()
            ->when($filters['equipo'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereRelation('team', 'slug', $slug))
            ->when($filters['tipo'] ?? [], fn (Builder $query, array $slugs) => $query
                ->whereHas('categories', fn (Builder $categories) => $categories->whereIn('slug', $slugs)))
            ->when($filters['region'] ?? [], fn (Builder $query, array $slugs) => $query
                ->whereHas('categories', fn (Builder $categories) => $categories->whereIn('slug', $slugs)))
            ->when($filters['pais'] ?? [], fn (Builder $query, array $codes) => $query
                ->whereHas('team', fn (Builder $teams) => $teams->whereIn('country_code', $codes)))
            ->when($priceMinCents !== null, fn (Builder $query) => $query
                ->where('base_price_cents', '>=', $priceMinCents))
            ->when($priceMaxCents !== null, fn (Builder $query) => $query
                ->where('base_price_cents', '<=', $priceMaxCents))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query
                ->where(function (Builder $sub) use ($term): void {
                    $escaped = '%'.addcslashes($term, '%_\\').'%';

                    $sub->where('name', 'like', $escaped)
                        ->orWhereRelation('team', 'name', 'like', $escaped)
                        ->orWhere('season', 'like', $escaped);
                }));
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, array{value: string, label: string, count: int}>
     */
    private function categoryOptions(array $labels): array
    {
        $counts = Category::query()
            ->whereIn('slug', array_keys($labels))
            ->withCount(['products as published_products_count' => fn (Builder $query) => $query->published()])
            ->get()
            ->keyBy('slug');

        return collect($labels)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'count' => (int) ($counts->get($value)->published_products_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{value: string, label: string, count: int}> */
    private function countryOptions(): array
    {
        return Product::query()
            ->published()
            ->join('teams', 'teams.id', '=', 'products.team_id')
            ->whereNotNull('teams.country_code')
            ->select('teams.country_code as country_code')
            ->selectRaw('MAX(teams.sportsdb_country) as remote_name')
            ->selectRaw('COUNT(products.id) as products_count')
            ->groupBy('teams.country_code')
            ->get()
            ->map(function (object $row): array {
                $code = strtoupper((string) $row->country_code);

                return [
                    'value' => $code,
                    'label' => $this->countryLabel($code, is_string($row->remote_name) ? $row->remote_name : null),
                    'count' => (int) $row->products_count,
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function countryLabel(string $code, ?string $fallback): string
    {
        if (class_exists(\Locale::class)) {
            $translated = \Locale::getDisplayRegion('-'.$code, 'es_ES');

            if (is_string($translated) && $translated !== '' && $translated !== $code) {
                return $translated;
            }
        }

        return self::COUNTRY_LABELS[$code] ?? $fallback ?? $code;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array{value: string, label: string, count: int}>  $countryOptions
     * @return array<int, array{name: string, value: string, label: string}>
     */
    private function activeFilterPills(array $filters, array $countryOptions, ?Team $team): array
    {
        $pills = [];
        $countryLabels = collect($countryOptions)->pluck('label', 'value');

        foreach ($filters['tipo'] ?? [] as $value) {
            $pills[] = ['name' => 'tipo[]', 'value' => $value, 'label' => self::TYPE_LABELS[$value]];
        }

        foreach ($filters['region'] ?? [] as $value) {
            $pills[] = ['name' => 'region[]', 'value' => $value, 'label' => self::REGION_LABELS[$value]];
        }

        foreach ($filters['pais'] ?? [] as $value) {
            $pills[] = [
                'name' => 'pais[]',
                'value' => $value,
                'label' => (string) ($countryLabels->get($value) ?? $value),
            ];
        }

        if ($team) {
            $pills[] = ['name' => 'equipo', 'value' => $team->slug, 'label' => $team->name];
        }

        if (! empty($filters['q'])) {
            $pills[] = ['name' => 'q', 'value' => (string) $filters['q'], 'label' => '“'.$filters['q'].'”'];
        }

        if (isset($filters['precio_min'])) {
            $pills[] = [
                'name' => 'precio_min',
                'value' => (string) $filters['precio_min'],
                'label' => 'Desde '.number_format((float) $filters['precio_min'], 2, ',', '.').' €',
            ];
        }

        if (isset($filters['precio_max'])) {
            $pills[] = [
                'name' => 'precio_max',
                'value' => (string) $filters['precio_max'],
                'label' => 'Hasta '.number_format((float) $filters['precio_max'], 2, ',', '.').' €',
            ];
        }

        return $pills;
    }

    public function show(Product $product): View
    {
        // El route model binding resuelve por slug SIN aplicar el scope
        // published(). Sin esta línea, un borrador es visible para quien
        // adivine la URL.
        abort_unless($product->is_active && $product->published_at?->isPast(), 404);

        $product->load(['team', 'teamEquipment', 'images', 'variants'])
            ->loadAvg('approvedReviews', 'rating')
            ->loadCount('approvedReviews');

        $reviews = $product->approvedReviews()
            ->with('user:id,name')
            ->latest('published_at')
            ->limit(12)
            ->get();

        $reviewableOrderItem = null;
        $existingReview = null;

        if ($user = request()->user()) {
            $existingReview = $product->reviews()->where('user_id', $user->id)->latest()->first();

            $reviewableOrderItem = OrderItem::query()
                ->with('order')
                ->where('product_id', $product->id)
                ->whereDoesntHave('review')
                ->whereHas('order', fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->whereIn('status', [
                        OrderStatus::Paid->value,
                        OrderStatus::Shipped->value,
                        OrderStatus::Delivered->value,
                    ]))
                ->latest()
                ->first();
        }

        return view('shop.show', [
            'product' => $product,
            'variants' => $product->variants->sortBy(fn ($v) => $v->size->position()),
            'reviews' => $reviews,
            'reviewableOrderItem' => $reviewableOrderItem,
            'existingReview' => $existingReview,
            'related' => Product::published()
                ->where('team_id', $product->team_id)
                ->whereKeyNot($product->id)
                ->with(['team', 'primaryImage'])
                ->limit(4)
                ->get(),
        ]);
    }

    public function home(): View
    {
        // El hero debe ser estable y reconocible: buscamos una equipación
        // publicada del Real Madrid. La vista dispone de una imagen local de
        // respaldo para no sustituirla accidentalmente por otro equipo.
        $hero = Product::published()
            ->whereHas('team', fn ($query) => $query
                ->where('slug', 'real-madrid-cf')
                ->orWhere('name', 'like', 'Real Madrid%'))
            ->with(['team', 'primaryImage'])
            ->orderByRaw("CASE WHEN season = '2019-2020' THEN 0 ELSE 1 END")
            ->latest('published_at')
            ->first();

        return view('shop.home', [
            'catalogCount' => Product::published()->count(),
            'hero' => $hero,
            'newest' => Product::published()
                ->with(['team', 'primaryImage'])
                ->withSum('variants as stock_total', 'stock')
                ->latest('published_at')
                ->limit(8)
                ->get(),
            'teams' => Team::withCount(['products' => fn ($q) => $q->published()])
                ->whereHas('products', fn ($q) => $q->published())
                ->orderByDesc('products_count')
                ->limit(8)
                ->get(),
            'catalogStats' => [
                'products' => Product::published()->count(),
                'teams' => Team::whereHas('products', fn ($q) => $q->published())->count(),
                'seasons' => Product::published()->distinct()->count('season'),
            ],
        ]);
    }
}
