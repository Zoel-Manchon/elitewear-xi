<?php

namespace App\Console\Commands;

use App\Models\TeamEquipment;
use App\Services\SportsData\RemoteImageStore;
use App\Services\SportsData\TheSportsDbClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Recorre la descarga de UNA imagen paso a paso y dice en cuál falla.
 *
 * El importador captura las excepciones por lote y sigue: eso está bien para
 * que un fallo aislado no tumbe una importación de horas, pero es inútil para
 * depurar. Esto hace lo contrario — una sola imagen, cada paso a la vista.
 */
class TestImagePipeline extends Command
{
    protected $signature = 'catalog:test-image {--url= : Probar una URL concreta}';

    protected $description = 'Diagnostica la descarga y el guardado de una imagen';

    public function handle(TheSportsDbClient $client, RemoteImageStore $store): int
    {
        // --- 0. Entorno ------------------------------------------------------
        $this->line('<comment>Entorno</comment>');
        $this->line('  PHP            '.PHP_VERSION);
        $this->line('  gd             '.(extension_loaded('gd') ? '<info>sí</info>' : '<error>NO</error>'));
        $this->line('  imagick        '.(extension_loaded('imagick') ? 'sí' : 'no'));

        $this->line('  imagewebp      '.(function_exists('imagewebp') ? '<info>sí</info>' : 'no · se guardará en PNG'));
        $this->newLine();

        // --- 1. Origen -------------------------------------------------------
        $url = $this->option('url');

        if (! $url) {
            $equipment = TeamEquipment::query()
                ->where('provider', 'thesportsdb')
                ->whereNotNull('image_url')
                ->first();

            if (! $equipment) {
                $this->error('No hay ninguna equipación con image_url en la base de datos.');
                $this->line('Ejecuta antes: php artisan catalog:sportsdb-full --max-countries=2');

                return self::FAILURE;
            }

            $url = $equipment->image_url;
            $this->line("<comment>Equipación</comment> id externo {$equipment->external_equipment_id}");
        }

        $this->line("<comment>URL</comment> {$url}");
        $this->newLine();

        // --- 2. Descarga -----------------------------------------------------
        try {
            $download = $client->download($url);
            $bytes = $download['body'];
            $this->info('  [1/3] Descarga OK · '.number_format(strlen($bytes)).' bytes · extensión '.$download['extension']);
        } catch (Throwable $e) {
            $this->error('  [1/3] Descarga FALLA');
            $this->line('        '.class_basename($e).': '.$e->getMessage());

            return self::FAILURE;
        }

        // --- 3. Validación ---------------------------------------------------
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            $this->error('  [2/3] Los bytes no son una imagen reconocible');
            $this->line('        Primeros bytes: '.bin2hex(substr($bytes, 0, 12)));

            return self::FAILURE;
        }

        $this->info("  [2/3] Imagen válida · {$info[0]}x{$info[1]} · ".image_type_to_mime_type($info[2]));

        // --- 4. Reencodeado y guardado ---------------------------------------
        try {
            $path = $store->put('diagnostico', 'prueba', 'test-1', $bytes);
            $size = Storage::disk('public')->size($path);

            $this->info("  [3/3] Guardada OK · {$path} · ".number_format($size).' bytes');
            Storage::disk('public')->delete($path);
        } catch (Throwable $e) {
            $this->error('  [3/3] Guardado FALLA');
            $this->line('        '.class_basename($e).': '.$e->getMessage());
            $this->newLine();
            $this->line('<comment>Traza:</comment>');
            $this->line('  '.str_replace("\n", "\n  ", substr($e->getTraceAsString(), 0, 900)));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Los tres pasos funcionan. Si el importador sigue omitiendo, el fallo está en el reconciler.');

        return self::SUCCESS;
    }
}
