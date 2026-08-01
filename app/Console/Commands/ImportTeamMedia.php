<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\SportsData\RemoteImageStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class ImportTeamMedia extends Command
{
    protected $signature = 'teams:media
        {--team= : Limitar a un equipo por slug}
        {--limit=40 : Máximo de equipos}
        {--force : Rehacer los que ya tienen medios}';

    protected $description = 'Descarga banner y fanart de los equipos desde TheSportsDB';

    public function handle(RemoteImageStore $images): int
    {
        $teams = Team::query()
            ->whereNotNull('sportsdb_id')
            ->when($this->option('team'), fn ($q, $slug) => $q->where('slug', $slug))
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('media_synced_at'))
            ->withCount('products')
            ->orderByDesc('products_count')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($teams->isEmpty()) {
            $this->info('Nada que descargar. Usa --force para rehacer.');

            return self::SUCCESS;
        }

        $descargados = 0;

        foreach ($teams as $team) {
            $cambios = ['media_synced_at' => now()];

            foreach ([
                'banner_path' => $team->sportsdb_banner_url,
                'fanart_path' => $team->sportsdb_fanart_url
                    ?: (($team->sportsdb_fanart_urls ?? [])[0] ?? null),
            ] as $columna => $url) {
                if (! $url) {
                    continue;
                }

                try {
                    $respuesta = Http::timeout(20)->get($url);

                    if (! $respuesta->successful()) {
                        continue;
                    }

                    // Mismo camino endurecido que las equipaciones: nombre
                    // generado por nosotros, verificación de que es imagen
                    // real y reencodeado a WEBP.
                    $cambios[$columna] = $images->put(
                        directory: 'teams',
                        slug: $team->slug.'-'.str_replace('_path', '', $columna),
                        externalId: (string) $team->sportsdb_id,
                        bytes: $respuesta->body(),
                    );

                    $descargados++;
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $team->update($cambios);
            $this->line("  {$team->name}: ".(count($cambios) - 1).' medios');
        }

        $this->newLine();
        $this->info("{$descargados} ficheros descargados de {$teams->count()} equipos.");

        return self::SUCCESS;
    }
}
