@php
    $banner = $team->bannerUrl() ?? $team->fanartUrl();
    $youtube = $team->youtubeId();
@endphp

<section class="team-header {{ $banner ? '' : 'team-header--plain' }}">
    @if ($banner)
        <img src="{{ $banner }}" alt="" class="team-header__bg" loading="lazy" decoding="async">
        <span class="team-header__veil" aria-hidden="true"></span>
    @endif

    <div class="team-header__body">
        @if ($team->sportsdb_badge_url)
            <img src="{{ $team->sportsdb_badge_url }}" alt="" class="team-header__badge" loading="lazy">
        @endif

        <div>
            <p class="eyebrow mb-1">
                {{ $team->sportsdb_country }}
                @if ($team->sportsdb_formed_year) · desde {{ $team->sportsdb_formed_year }} @endif
            </p>

            <h2 class="team-header__name">{{ $team->name }}</h2>

            @if ($team->sportsdb_stadium)
                <p class="team-header__meta data mb-0">
                    {{ $team->sportsdb_stadium }}
                    @if ($team->sportsdb_stadium_capacity)
                        · {{ number_format($team->sportsdb_stadium_capacity, 0, ',', '.') }} localidades
                    @endif
                </p>
            @endif
        </div>

        @if ($youtube)
            {{-- El id se extrae y se valida en el modelo: la URL del tercero
                 nunca se incrusta tal cual. --}}
            <a class="btn btn-sm btn-outline-light ms-auto"
               href="https://www.youtube.com/watch?v={{ $youtube }}"
               target="_blank" rel="noopener noreferrer">
                Vídeo del club
            </a>
        @endif
    </div>
</section>
