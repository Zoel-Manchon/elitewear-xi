<?php

namespace App\Services\SportsData;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda una imagen descargada de un tercero.
 *
 * Centraliza tres controles:
 *
 *  1. El nombre de fichero se construye SOLO con caracteres seguros. El
 *     identificador externo venía de la respuesta de la API y se concatenaba
 *     tal cual: un id con "../" escribe fuera del directorio previsto.
 *  2. Los bytes se verifican como imagen real. No se confía en la cabecera
 *     Content-Type, que la controla el servidor remoto.
 *  3. Se reencodean, lo que descarta metadatos y cualquier carga útil añadida
 *     detrás de una cabecera de imagen válida.
 *
 * Es el mismo tratamiento que reciben las subidas del panel de admin: una
 * imagen que llega por HTTP no merece más confianza que una que sube un
 * usuario.
 */
final class RemoteImageStore
{
    private const MAX_PIXELS = 40_000_000;   // ~6300x6300: frena las "image bombs"

    private const MAX_WIDTH = 1400;

    private const QUALITY = 82;

    public function put(string $directory, string $slug, string $externalId, string $bytes): string
    {
        $this->assertRealImage($bytes);

        [$encoded, $extension] = $this->encode($bytes);

        $name = $this->safeName($slug, $externalId);
        $path = trim($directory, '/')."/{$name}.{$extension}";

        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    /**
     * Reencodea la imagen con GD.
     *
     * Aquí había una llamada a Intervention Image, y costó tres rondas de
     * depuración: la API cambió entre versiones (make(), luego read()) y la
     * instalada no exponía ninguna de las dos. Para lo que hace falta —
     * redimensionar y reencodear— GD basta, ya es una dependencia obligatoria
     * del proyecto, y no puede romperse por una actualización menor de un
     * paquete.
     *
     * El reencodeado es el control de seguridad: descarta metadatos y
     * cualquier carga útil añadida detrás de una cabecera de imagen válida.
     *
     * @return array{0: string, 1: string} contenido y extensión
     */
    private function encode(string $bytes): array
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            throw new RuntimeException('GD no pudo decodificar la imagen.');
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);

            if ($width > self::MAX_WIDTH) {
                $scaled = imagescale($source, self::MAX_WIDTH);

                if ($scaled !== false) {
                    imagedestroy($source);
                    $source = $scaled;
                }
            }

            // La transparencia se pierde al reencodear si no se preserva
            // explícitamente, y las equipaciones vienen en PNG con fondo
            // transparente.
            imagepalettetotruecolor($source);
            imagealphablending($source, false);
            imagesavealpha($source, true);

            if (function_exists('imagewebp')) {
                ob_start();
                $ok = imagewebp($source, null, self::QUALITY);
                $contenido = (string) ob_get_clean();

                if ($ok && $contenido !== '') {
                    return [$contenido, 'webp'];
                }
            }

            // Sin soporte WEBP en la compilación de GD, PNG conserva la
            // transparencia. Pesa más, pero funciona en cualquier sitio.
            ob_start();
            $ok = imagepng($source, null, 6);
            $contenido = (string) ob_get_clean();

            if (! $ok || $contenido === '') {
                throw new RuntimeException('GD no pudo reencodear la imagen.');
            }

            return [$contenido, 'png'];
        } finally {
            // En PHP 8 imagecreatefromstring() devuelve GdImage o false, y el
            // false ya se descartó arriba: no hace falta comprobar el tipo.
            imagedestroy($source);
        }
    }

    /**
     * Nombre construido por nosotros. Nunca se concatena texto de un tercero
     * sin filtrar: ni el id externo ni el nombre original del fichero.
     */
    private function safeName(string $slug, string $externalId): string
    {
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $externalId) ?: 'sin-id';

        return Str::slug($slug).'-'.substr($id, 0, 40).'-'.Str::random(6);
    }

    private function assertRealImage(string $bytes): void
    {
        if ($bytes === '') {
            throw new RuntimeException('La descarga vino vacía.');
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw new RuntimeException('El fichero descargado no es una imagen válida.');
        }

        [$width, $height, $type] = $info;

        if (! in_array($type, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Formato de imagen no permitido: '.image_type_to_mime_type($type));
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw new RuntimeException("Dimensiones desproporcionadas: {$width}x{$height}.");
        }

        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            throw new RuntimeException(
                'Falta la extensión gd o imagick: sin ella no se puede reencodear.'
            );
        }
    }
}
