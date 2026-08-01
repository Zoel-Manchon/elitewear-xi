@extends('layouts.app')

@section('title', $product->name)
@section('meta_description', Str::limit(strip_tags($product->description ?: $product->name.' · '.$product->team->name.' · '.$product->season), 155))
@section('canonical', route('products.show', $product))
@section('og_image', $product->images->first()?->url() ?? asset('images/placeholder.svg'))
@section('og_type', 'product')

@php
    $reviewCount = (int) $product->approved_reviews_count;
    $reviewAverage = $reviewCount > 0 ? round((float) $product->approved_reviews_avg_rating, 1) : null;
    $productSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => strip_tags($product->description ?: $product->name),
        'image' => $product->images->isNotEmpty()
            ? $product->images->map(fn ($image) => $image->url())->values()->all()
            : [asset('images/placeholder.svg')],
        'sku' => $variants->first()?->sku,
        'brand' => ['@type' => 'Brand', 'name' => 'Elitewear XI'],
        'category' => 'Camisetas de fútbol de fútbol',
        'offers' => [
            '@type' => 'Offer',
            'url' => route('products.show', $product),
            'priceCurrency' => $product->currency,
            'price' => number_format($product->base_price_cents / 100, 2, '.', ''),
            'availability' => $variants->sum('stock') > 0
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ],
    ];
    if ($reviewAverage) {
        $productSchema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $reviewAverage,
            'reviewCount' => $reviewCount,
            'bestRating' => 5,
        ];
    }
    $breadcrumbsSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Catálogo', 'item' => route('products.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $product->team->name, 'item' => route('products.index', ['equipo' => $product->team->slug])],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $product->name, 'item' => route('products.show', $product)],
        ],
    ];
@endphp

@push('structured_data')
<script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($breadcrumbsSchema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
<div class="container py-4">
    <nav aria-label="Migas de pan">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Catálogo</a></li>
            <li class="breadcrumb-item"><a href="{{ route('products.index', ['equipo' => $product->team->slug]) }}">{{ $product->team->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $product->season ?: 'Temporada no indicada' }}</li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <div class="gallery" data-gallery>
                <div class="gallery__thumbs">
                    @foreach ($product->images as $image)
                        <button type="button" class="gallery__thumb" data-gallery-thumb
                                data-full="{{ $image->url() }}" data-alt="{{ $image->alt }}"
                                data-width="1400" data-height="1750"
                                aria-current="{{ $loop->first ? 'true' : 'false' }}"
                                aria-label="Ver imagen {{ $loop->iteration }} de {{ $product->images->count() }}">
                            <img src="{{ $image->url() }}" alt="" loading="lazy">
                        </button>
                    @endforeach
                </div>

                <div class="gallery__stage" data-gallery-stage tabindex="0" role="button" aria-label="Ampliar la imagen">
                    <img src="{{ $product->images->first()?->url() ?? asset('images/placeholder.svg') }}"
                         alt="{{ $product->images->first()?->alt ?? $product->name }}" data-gallery-main>
                    <div class="gallery__lens" data-gallery-lens aria-hidden="true"></div>
                    <span class="gallery__hint">Pasa el cursor para ampliar · clic para pantalla completa</span>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <p class="eyebrow mb-2">{{ $product->team->name }} · {{ $product->season ?: 'Temporada no indicada' }} · {{ $product->kit_type->label() }}</p>
            <h1 class="h2 mb-2">{{ $product->name }}</h1>

            @if ($reviewAverage)
                <a href="#resenas" class="rating-summary mb-3">
                    <span aria-label="{{ $reviewAverage }} de 5 estrellas">★ {{ number_format($reviewAverage, 1, ',', '.') }}</span>
                    <span>{{ $reviewCount }} {{ $reviewCount === 1 ? 'reseña verificada' : 'reseñas verificadas' }}</span>
                </a>
            @endif

            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <p class="price fs-2 mb-0">{{ $product->basePrice()->format() }}</p>
                <button type="button" class="btn btn-outline-light wishlist-product-button" data-wishlist-toggle
                        data-product-id="{{ $product->id }}" aria-pressed="false">
                    <svg width="19" height="19" aria-hidden="true"><use href="#icon-heart"/></svg>
                    <span>Guardar</span>
                </button>
            </div>

            @if ($product->description)<p class="text-body-secondary">{{ $product->description }}</p>@endif
            @error('quantity')<div class="alert alert-warning py-2">{{ $message }}</div>@enderror

            <form method="POST" action="{{ route('cart.items.store') }}" class="mt-4" data-add-to-cart>
                @csrf
                <fieldset class="mb-4">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <legend class="eyebrow float-none w-auto mb-0">Talla</legend>
                        <button type="button" class="btn btn-link btn-sm p-0 small" data-bs-toggle="modal" data-bs-target="#sizeGuide">Guía de tallas</button>
                    </div>
                    <div class="size-grid">
                        @foreach ($variants as $variant)
                            <div class="size-option">
                                <input type="radio" name="product_variant_id" id="size-{{ $variant->id }}" value="{{ $variant->id }}"
                                       @disabled(! $variant->isAvailable()) @checked($loop->first && $variant->isAvailable())>
                                <label for="size-{{ $variant->id }}">
                                    {{ $variant->size->value }}
                                    @if ($variant->stock > 0 && $variant->stock <= 3)
                                        <small>quedan {{ $variant->stock }}</small>
                                    @elseif ($variant->stock === 0)
                                        <small>agotada</small>
                                    @elseif ($variant->price_delta_cents !== 0)
                                        <small>+{{ number_format($variant->price_delta_cents / 100, 2, ',', '.') }} €</small>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                @php $maxStock = $variants->max(fn ($variant) => $variant->stock); @endphp
                @if ($maxStock === 0)
                    <button class="btn btn-secondary btn-lg w-100" disabled>Agotada en todas las tallas</button>
                @else
                    <div class="d-flex gap-2">
                        <label class="visually-hidden" for="quantity">Cantidad</label>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" max="10" class="form-control" style="width:88px">
                        <button type="submit" class="btn btn-primary btn-lg flex-grow-1">Añadir al carrito</button>
                    </div>
                @endif
            </form>

            <div class="purchase-assurance" aria-label="Condiciones de compra">
                <div><strong>24-48 h</strong><span>Envío peninsular</span></div>
                <div><strong>30 días</strong><span>Para devolver</span></div>
                <div><strong>Sin cuenta</strong><span>Checkout invitado</span></div>
            </div>

            @php $agotadas = $variants->filter(fn ($variant) => $variant->stock === 0); @endphp
            @if ($agotadas->isNotEmpty())
                <section class="restock">
                    <div class="restock__head"><svg width="18" height="18"><use href="#icon-bell"/></svg><span class="eyebrow" style="color:inherit">Avísame cuando vuelva</span></div>
                    <p class="small text-body-secondary mb-3">Te escribimos una sola vez cuando esa talla vuelva a estar disponible.</p>
                    <form method="POST" action="{{ route('stock-alerts.store') }}" class="row g-2" data-stock-alert>
                        @csrf
                        <div class="col-4">
                            <label class="visually-hidden" for="alert_variant">Talla agotada</label>
                            <select class="form-select form-select-sm" id="alert_variant" name="product_variant_id" required>
                                @foreach ($agotadas as $variant)<option value="{{ $variant->id }}">Talla {{ $variant->size->value }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-8">
                            <div class="input-group input-group-sm">
                                <label class="visually-hidden" for="alert_email">Tu correo</label>
                                <input type="email" class="form-control" id="alert_email" name="email" placeholder="tu@correo.com" value="{{ auth()->user()?->email }}" required>
                                <button class="btn btn-outline-light">Avisadme</button>
                            </div>
                        </div>
                        <div class="col-12" data-stock-alert-feedback></div>
                    </form>
                </section>
            @endif

            <div class="product-disclosure mt-4" id="productDetails">
                <details open><summary>Ficha de catálogo</summary><p>Referencia de equipación documentada por TheSportsDB. La temporada y el tipo proceden directamente del registro remoto.</p></details>
                <details><summary>Cuidados</summary><p>Lavar del revés a 30 °C, sin lejía ni secadora. No planchar la estampación.</p></details>
                <details><summary>Tallas y ajuste</summary><p>Ajuste regular. Compara una camiseta que ya uses con la guía de medidas.</p></details>
                @if ($product->teamEquipment)
                    @php
                        $equipmentSource = $product->teamEquipment;
                        $sourceHref = 'https://www.thesportsdb.com';
                    @endphp
                    <details>
                        <summary>Referencia de la equipación</summary>
                        <p class="mb-2">
                            Temporada {{ $equipmentSource->season ?: $product->season }} ·
                            {{ $equipmentSource->typeLabel() }}.
                        </p>
                        <p class="small text-body-secondary mb-1">
                            Referencia visual de
                            <a href="{{ $sourceHref }}" target="_blank" rel="noopener noreferrer">TheSportsDB</a>
                            @if ($equipmentSource->contributor)
                                · autoría: {{ $equipmentSource->contributor }}
                            @endif
                            .
                        </p>
                        <dl class="row small mt-3 mb-2">
                            <dt class="col-5">ID de equipación</dt>
                            <dd class="col-7 data">{{ $equipmentSource->external_equipment_id }}</dd>
                            <dt class="col-5">Tipo original</dt>
                            <dd class="col-7">{{ $equipmentSource->equipment_type ?: '—' }}</dd>
                            <dt class="col-5">Añadida a la fuente</dt>
                            <dd class="col-7">{{ $equipmentSource->source_created_at?->format('d/m/Y') ?: '—' }}</dd>
                            <dt class="col-5">Colaborador</dt>
                            <dd class="col-7">{{ $equipmentSource->contributor ?: 'No indicado' }}</dd>
                        </dl>
                        <p class="small text-body-secondary mb-0">
                            La imagen identifica la equipación registrada por TheSportsDB; no certifica por sí sola las características físicas de una unidad concreta.
                        </p>
                    </details>
                @endif
            </div>
        </div>
    </div>

    @php
        $teamDescription = $product->team->sportsDbDescription();
        $teamColours = array_values(array_filter([
            $product->team->sportsdb_colour_1,
            $product->team->sportsdb_colour_2,
            $product->team->sportsdb_colour_3,
        ]));
    @endphp

    <section class="mt-5 pt-4" aria-labelledby="team-profile-title">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-1">Información de TheSportsDB</p>
                <h2 class="h3 mb-0" id="team-profile-title">{{ $product->team->sportsdb_name ?: $product->team->name }}</h2>
            </div>
            @if ($product->team->sportsdb_badge_url)
                <img src="{{ $product->team->sportsdb_badge_url }}" alt="Escudo de {{ $product->team->name }}" width="72" height="72" loading="lazy" style="object-fit:contain">
            @endif
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="p-4 h-100" style="border:1px solid var(--line);background:var(--pitch-900)">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Equipo</dt><dd class="col-7">{{ $product->team->sportsdb_name ?: $product->team->name }}</dd>
                        <dt class="col-5">Fundación</dt><dd class="col-7">{{ $product->team->sportsdb_formed_year ?: '—' }}</dd>
                        <dt class="col-5">País</dt><dd class="col-7">{{ $product->team->sportsdb_country ?: $product->team->country_code ?: '—' }}</dd>
                        <dt class="col-5">Liga</dt><dd class="col-7">{{ $product->team->sportsdb_league ?: '—' }}</dd>
                        <dt class="col-5">Estadio</dt><dd class="col-7">{{ $product->team->sportsdb_stadium ?: '—' }}</dd>
                        <dt class="col-5">Capacidad</dt><dd class="col-7">{{ $product->team->sportsdb_stadium_capacity ? number_format($product->team->sportsdb_stadium_capacity, 0, ',', '.') : '—' }}</dd>
                        <dt class="col-5">Ubicación</dt><dd class="col-7">{{ $product->team->sportsdb_location ?: '—' }}</dd>
                        @if ($product->team->sportsdb_keywords)
                            <dt class="col-5">Apodos</dt><dd class="col-7">{{ $product->team->sportsdb_keywords }}</dd>
                        @endif
                    </dl>
                    @if ($teamColours)
                        <div class="d-flex align-items-center gap-2 mt-3" aria-label="Colores del equipo">
                            <span class="small text-body-secondary">Colores:</span>
                            @foreach ($teamColours as $colour)
                                <span title="{{ $colour }}" style="display:inline-block;width:24px;height:24px;border-radius:50%;background:{{ $colour }};border:1px solid var(--line)"></span>
                            @endforeach
                        </div>
                    @endif
                    @if ($product->team->sportsDbWebsiteUrl())
                        <a class="btn btn-sm btn-outline-light mt-3" href="{{ $product->team->sportsDbWebsiteUrl() }}" target="_blank" rel="noopener noreferrer">Sitio web del equipo</a>
                    @endif
                </div>
            </div>
            <div class="col-lg-7">
                <div class="p-4 h-100" style="border:1px solid var(--line);background:var(--pitch-900)">
                    <h3 class="h5">Contexto del equipo</h3>
                    @if ($teamDescription)
                        <p class="text-body-secondary mb-0" style="white-space:pre-line">{{ Str::limit($teamDescription, 1800) }}</p>
                    @else
                        <p class="text-body-secondary mb-0">TheSportsDB no proporciona todavía una descripción para este equipo.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section id="resenas" class="reviews-section mt-5 pt-5">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-1">Opiniones de compradores</p>
                <h2 class="h3 mb-0">Reseñas verificadas</h2>
            </div>
            @if ($reviewAverage)<div class="review-score"><strong>{{ number_format($reviewAverage, 1, ',', '.') }}</strong><span>de 5 · {{ $reviewCount }}</span></div>@endif
        </div>

        @auth
            @if ($reviewableOrderItem)
                <form method="POST" action="{{ route('reviews.store', $product) }}" class="review-form mb-5">
                    @csrf
                    <input type="hidden" name="order_item_id" value="{{ $reviewableOrderItem->id }}">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label eyebrow" for="rating">Valoración</label>
                            <select class="form-select" id="rating" name="rating" required>
                                @foreach ([5,4,3,2,1] as $rating)<option value="{{ $rating }}">{{ str_repeat('★', $rating) }} ({{ $rating }}/5)</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label eyebrow" for="review_title">Título</label>
                            <input class="form-control" id="review_title" name="title" maxlength="120" value="{{ old('title') }}" placeholder="Resume tu experiencia">
                        </div>
                        <div class="col-12">
                            <label class="form-label eyebrow" for="review_body">Tu opinión</label>
                            <textarea class="form-control @error('body') is-invalid @enderror" id="review_body" name="body" rows="4" minlength="20" maxlength="2000" required>{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <span class="verified-purchase">✓ Compra verificada · pedido {{ $reviewableOrderItem->order->number }}</span>
                            <button class="btn btn-primary">Publicar reseña</button>
                        </div>
                    </div>
                </form>
            @elseif ($existingReview)
                <p class="text-body-secondary">Ya has publicado una reseña verificada de esta camiseta.</p>
            @endif
        @else
            <p class="text-body-secondary">Las reseñas solo pueden publicarlas compradores autenticados con un pedido pagado.</p>
        @endauth

        @if ($reviews->isEmpty())
            <p class="text-body-secondary">Todavía no hay reseñas. La primera solo podrá escribirla una compra verificada.</p>
        @else
            <div class="row g-3">
                @foreach ($reviews as $review)
                    <div class="col-md-6">
                        <article class="review-card h-100">
                            <div class="d-flex justify-content-between gap-3 mb-2">
                                <div><strong>{{ $review->title ?: 'Compra verificada' }}</strong><span class="d-block small text-body-secondary">{{ $review->user->name }} · {{ $review->published_at->format('d/m/Y') }}</span></div>
                                <span class="review-stars" aria-label="{{ $review->rating }} de 5 estrellas">{{ str_repeat('★', $review->rating) }}</span>
                            </div>
                            <p class="mb-3">{{ $review->body }}</p>
                            <span class="verified-purchase">✓ Compra verificada</span>
                        </article>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($related->isNotEmpty())
        <section class="mt-5 pt-5" style="border-top:1px solid var(--line)">
            <p class="eyebrow mb-1">También del {{ $product->team->name }}</p>
            <h2 class="h4 mb-4">Otras temporadas</h2>
            <div class="row g-3 g-md-4">
                @foreach ($related as $item)<div class="col-6 col-md-3"><x-product-card :product="$item"/></div>@endforeach
            </div>
        </section>
    @endif
</div>

@include('partials.size-guide')
<div class="buy-bar" data-buy-bar>
    <div class="flex-grow-1"><div class="data small text-body-secondary text-truncate">{{ $product->team->name }}</div><div class="price">{{ $product->basePrice()->format() }}</div></div>
    <button class="btn btn-primary" type="button" data-buy-bar-action>Elegir talla</button>
</div>
@endsection
