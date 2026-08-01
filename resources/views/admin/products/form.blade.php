@extends('layouts.admin')

@section('eyebrow', 'Catálogo')
@section('title', $product->exists ? 'Editar producto' : 'Nuevo producto')

@section('content')
<form method="POST"
      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="row g-4 g-xl-5">
        <div class="col-xl-7">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label eyebrow" for="name">Nombre</label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="{{ old('name', $product->name) }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label eyebrow" for="season">Temporada</label>
                    <input type="text" class="form-control" id="season" name="season"
                           value="{{ old('season', $product->season) }}" placeholder="1994-95">
                </div>

                <div class="col-md-6">
                    <label class="form-label eyebrow" for="team_id">Equipo</label>
                    <select class="form-select" id="team_id" name="team_id" required>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected(old('team_id', $product->team_id) == $team->id)>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label eyebrow" for="kit_type">Equipación</label>
                    <select class="form-select" id="kit_type" name="kit_type" required>
                        @foreach (\App\Enums\KitType::cases() as $kit)
                            <option value="{{ $kit->value }}"
                                @selected(old('kit_type', $product->kit_type?->value) === $kit->value)>
                                {{ $kit->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label eyebrow" for="base_price_eur">Precio base (€)</label>
                    <input type="number" step="0.01" min="0" class="form-control"
                           id="base_price_eur" name="base_price_eur"
                           value="{{ old('base_price_eur', number_format($product->base_price_cents / 100, 2, '.', '')) }}"
                           required>
                </div>

                <div class="col-md-6">
                    <label class="form-label eyebrow" for="slug">Slug (opcional)</label>
                    <input type="text" class="form-control" id="slug" name="slug"
                           value="{{ old('slug', $product->slug) }}" placeholder="Se genera del nombre">
                </div>

                <div class="col-12">
                    <label class="form-label eyebrow" for="description">Descripción</label>
                    <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="col-12">
                    <p class="eyebrow mb-2">Categorías</p>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($categories as $category)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="categories[]"
                                       value="{{ $category->id }}" id="cat-{{ $category->id }}"
                                       @checked(in_array($category->id, old('categories', $selectedCategories)))>
                                <label class="form-check-label" for="cat-{{ $category->id }}">{{ $category->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-12 d-flex gap-4 pt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                               id="is_active" @checked(old('is_active', $product->is_active))>
                        <label class="form-check-label" for="is_active">Activo</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="published" value="1"
                               id="published" @checked(old('published', (bool) $product->published_at))>
                        <label class="form-check-label" for="published">Publicado</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="p-4 mb-4" style="border:1px solid var(--line);background:var(--pitch-900)">
                <p class="eyebrow mb-3">Tallas y stock</p>

                @foreach ($sizes as $index => $size)
                    @php $existing = $variants[$size->value] ?? null; @endphp
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <input type="hidden" name="variants[{{ $index }}][size]" value="{{ $size->value }}">
                        <span class="data" style="width:3rem">{{ $size->value }}</span>

                        <label class="visually-hidden" for="stock-{{ $size->value }}">Stock talla {{ $size->value }}</label>
                        <input type="number" min="0" class="form-control form-control-sm"
                               id="stock-{{ $size->value }}"
                               name="variants[{{ $index }}][stock]"
                               value="{{ old("variants.$index.stock", $existing->stock ?? 0) }}"
                               placeholder="Stock">

                        <label class="visually-hidden" for="delta-{{ $size->value }}">Suplemento talla {{ $size->value }}</label>
                        <input type="number" step="0.01" class="form-control form-control-sm"
                               id="delta-{{ $size->value }}"
                               name="variants[{{ $index }}][price_delta_eur]"
                               value="{{ old("variants.$index.price_delta_eur", number_format(($existing->price_delta_cents ?? 0) / 100, 2, '.', '')) }}"
                               placeholder="± €">
                    </div>
                @endforeach

                <p class="search-hint mt-3 mb-0">Stock y suplemento de precio por talla</p>
            </div>

            <div class="p-4" style="border:1px solid var(--line);background:var(--pitch-900)">
                <p class="eyebrow mb-3">Imágenes</p>

                @if ($product->exists && $product->images->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach ($product->images as $image)
                            <div class="position-relative">
                                <img src="{{ $image->url() }}" alt="" width="72" height="90"
                                     style="object-fit:cover;border:1px solid var(--line)">
                                @if ($image->is_primary)
                                    <span class="shirt-card__flag shirt-card__flag--low"
                                          style="font-size:.6rem;padding:.1rem .3rem">Principal</span>
                                @endif
                                <button form="del-img-{{ $image->id }}"
                                        class="btn btn-sm btn-link text-danger p-0 d-block w-100">Quitar</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <input type="file" class="form-control" name="images[]" multiple
                       accept="image/jpeg,image/png,image/webp" aria-label="Subir imágenes">

                <p class="search-hint mt-2 mb-0">
                    JPG, PNG o WEBP · mínimo 400×400 · máximo 6 por producto.
                    Se reconvierten a WEBP al guardarlas.
                </p>
            </div>

            @if ($product->exists)
                <div class="p-4 mt-4" style="border:1px solid var(--line);background:var(--pitch-900)">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <p class="eyebrow mb-1">TheSportsDB</p>
                            <p class="search-hint mb-0">
                                {{ $product->team->sportsdb_name ?: 'Equipo todavía no sincronizado' }}
                                @if ($product->team->sportsdb_id)
                                    · ID {{ $product->team->sportsdb_id }}
                                @endif
                            </p>
                        </div>
                        @if ($product->team->sportsdb_synced_at)
                            <span class="data small">{{ $product->team->sportsdb_synced_at->diffForHumans() }}</span>
                        @endif
                    </div>

                    @error('sportsdb')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
                    @enderror

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label eyebrow" for="sportsdb_id">ID TheSportsDB</label>
                            <input form="sportsdb-sync-form" type="number" min="1" class="form-control"
                                   id="sportsdb_id" name="sportsdb_id"
                                   value="{{ old('sportsdb_id', $product->team->sportsdb_id) }}"
                                   placeholder="Ej. 133738">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label eyebrow" for="sportsdb_query">Nombre de búsqueda</label>
                            <div class="input-group">
                                <input form="sportsdb-sync-form" type="text" class="form-control"
                                       id="sportsdb_query" name="sportsdb_query"
                                       value="{{ old('sportsdb_query', $product->team->sportsdb_query ?: $product->team->name) }}">
                                <button form="sportsdb-sync-form" class="btn btn-outline-light">Buscar equipaciones</button>
                            </div>
                        </div>
                    </div>

                    <p class="search-hint mb-3">
                        Con ID se usa el lookup directo. La clave gratuita solo permite buscar por nombre el ejemplo Arsenal
                        y devuelve hasta 2 registros por equipo. Usa catalog:sportsdb-full para descubrir equipos de ligas principales,
                        conservar todas las referencias ya vistas y aprovechar también strEquipment del perfil del equipo.
                    </p>

                    @if ($product->team->sportsdb_sync_error)
                        <div class="alert alert-warning py-2">{{ $product->team->sportsdb_sync_error }}</div>
                    @endif

                    @if ($product->team->sportsdb_name)
                        <details class="mb-3">
                            <summary class="search-hint">Ver perfil completo del equipo</summary>
                            <dl class="row small mt-3 mb-0">
                                <dt class="col-5">Nombre TheSportsDB</dt><dd class="col-7">{{ $product->team->sportsdb_name }}</dd>
                                <dt class="col-5">Fundación</dt><dd class="col-7">{{ $product->team->sportsdb_formed_year ?: '—' }}</dd>
                                <dt class="col-5">Liga</dt><dd class="col-7">{{ $product->team->sportsdb_league ?: '—' }}</dd>
                                <dt class="col-5">País</dt><dd class="col-7">{{ $product->team->sportsdb_country ?: '—' }}</dd>
                                <dt class="col-5">Estadio</dt><dd class="col-7">{{ $product->team->sportsdb_stadium ?: '—' }}</dd>
                                <dt class="col-5">Capacidad</dt><dd class="col-7">{{ $product->team->sportsdb_stadium_capacity ? number_format($product->team->sportsdb_stadium_capacity, 0, ',', '.') : '—' }}</dd>
                                <dt class="col-5">Ubicación</dt><dd class="col-7">{{ $product->team->sportsdb_location ?: '—' }}</dd>
                                <dt class="col-5">Web</dt><dd class="col-7">{{ $product->team->sportsdb_website ?: '—' }}</dd>
                            </dl>
                        </details>
                    @endif

                    @if ($product->team->equipment->isEmpty())
                        <p class="mb-0">No hay equipaciones importadas para este equipo.</p>
                    @else
                        <div class="d-grid gap-3">
                            @foreach ($product->team->equipment as $equipment)
                                <article class="p-3" style="border:1px solid var(--line)">
                                    <div class="d-flex gap-3 align-items-start">
                                        <img src="{{ $equipment->image_url }}"
                                             alt="Equipación {{ $equipment->season }} de {{ $product->team->name }}"
                                             width="92" height="112"
                                             loading="lazy"
                                             referrerpolicy="no-referrer"
                                             style="object-fit:contain;background:#fff;border:1px solid var(--line)">

                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex justify-content-between gap-2">
                                                <strong>{{ $equipment->season ?: 'Temporada desconocida' }}</strong>
                                                @if ($product->team_equipment_id === $equipment->id)
                                                    <span class="badge text-bg-success">Vinculada</span>
                                                @endif
                                            </div>
                                            <p class="mb-2">{{ $equipment->typeLabel() }}</p>

                                            <details>
                                                <summary class="search-hint">Ver los 7 campos recibidos</summary>
                                                <dl class="row small mt-2 mb-0">
                                                    <dt class="col-5">idEquipment</dt>
                                                    <dd class="col-7 data">{{ $equipment->external_equipment_id }}</dd>
                                                    <dt class="col-5">idTeam</dt>
                                                    <dd class="col-7 data">{{ $equipment->external_team_id }}</dd>
                                                    <dt class="col-5">date</dt>
                                                    <dd class="col-7 data">{{ $equipment->source_created_at?->format('Y-m-d H:i:s') ?: '—' }}</dd>
                                                    <dt class="col-5">strSeason</dt>
                                                    <dd class="col-7">{{ $equipment->season ?: '—' }}</dd>
                                                    <dt class="col-5">strEquipment</dt>
                                                    <dd class="col-7"><a href="{{ $equipment->image_url }}" target="_blank" rel="noopener noreferrer">Abrir imagen</a></dd>
                                                    <dt class="col-5">strType</dt>
                                                    <dd class="col-7">{{ $equipment->equipment_type ?: '—' }}</dd>
                                                    <dt class="col-5">strUsername</dt>
                                                    <dd class="col-7">{{ $equipment->contributor ?: '—' }}</dd>
                                                </dl>
                                            </details>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                        <button form="sportsdb-use-{{ $equipment->id }}"
                                                name="download" value="0"
                                                class="btn btn-sm btn-outline-light">Solo vincular</button>
                                        <button form="sportsdb-use-{{ $equipment->id }}"
                                                name="download" value="1"
                                                class="btn btn-sm btn-primary">Vincular y descargar</button>
                                        <label class="form-check ms-auto mb-0">
                                            <input form="sportsdb-use-{{ $equipment->id }}"
                                                   class="form-check-input" type="checkbox"
                                                   name="replace_primary" value="1">
                                            <span class="form-check-label small">Usar como principal</span>
                                        </label>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="d-grid gap-2 mt-4">
                <button class="btn btn-primary btn-lg">
                    {{ $product->exists ? 'Guardar cambios' : 'Crear producto' }}
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-light">Cancelar</a>
            </div>
        </div>
    </div>
</form>

{{-- Formularios de borrado fuera del principal: anidarlos no es HTML válido --}}
@if ($product->exists)
    <form id="sportsdb-sync-form" method="POST"
          action="{{ route('admin.products.sportsdb.sync', $product) }}">
        @csrf
    </form>

    @foreach ($product->team->equipment as $equipment)
        <form id="sportsdb-use-{{ $equipment->id }}" method="POST"
              action="{{ route('admin.products.sportsdb.use', [$product, $equipment]) }}">
            @csrf
        </form>
    @endforeach

    @foreach ($product->images as $image)
        <form id="del-img-{{ $image->id }}" method="POST"
              action="{{ route('admin.products.images.destroy', [$product, $image]) }}">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endif
@endsection
