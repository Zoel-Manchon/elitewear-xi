<?php

use App\Services\SportsData\RemoteImageStore;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // RemoteImageStore reencodea con Intervention Image, que necesita GD o
    // Imagick. Sin driver no se puede probar el guardado — y tampoco
    // funcionaría en producción, así que el aviso es intencionadamente claro.
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        test()->markTestSkipped(
            'Se necesita la extensión gd o imagick. Actívala en php.ini: extension=gd'
        );
    }

    Storage::fake('public');
});

/**
 * PNG 4x4 RGBA real, embebido en base64.
 *
 * No se genera con GD a propósito: si el driver de imagen faltara, el fallo
 * sería del propio test y no de lo que se quiere probar.
 */
function pngBytes(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAEklEQVR4nGP4z8DwHxkzkC4AADxA'
        .'H+HggXe0AAAAAElFTkSuQmCC'
    );
}

it('no permite salir del directorio con un id externo manipulado', function () {
    $path = app(RemoteImageStore::class)->put(
        directory: 'products/imported',
        slug: 'ajax-1994-95',
        externalId: '../../../../public/shell',
        bytes: pngBytes(),
    );

    expect($path)->toStartWith('products/imported/')
        ->and($path)->not->toContain('..')
        ->and($path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($path);
});

it('rechaza bytes que no son una imagen', function () {
    expect(fn () => app(RemoteImageStore::class)->put(
        directory: 'products/imported',
        slug: 'ajax',
        externalId: '123',
        bytes: '<?php system($_GET["c"]); ?>',
    ))->toThrow(RuntimeException::class);
});

it('rechaza un payload escondido tras una cabecera PNG valida', function () {
    $polyglot = substr(pngBytes(), 0, 8).'<?php system($_GET["c"]); ?>';

    expect(fn () => app(RemoteImageStore::class)->put(
        directory: 'products/imported',
        slug: 'ajax',
        externalId: '123',
        bytes: $polyglot,
    ))->toThrow(RuntimeException::class);
});

it('guarda siempre en webp', function () {
    $path = app(RemoteImageStore::class)->put(
        directory: 'products/retro',
        slug: 'ajax',
        externalId: 'commons-123',
        bytes: pngBytes(),
    );

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});
