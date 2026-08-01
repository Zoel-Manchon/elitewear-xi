<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Fanart 2-4 de la API, además del que ya se guardaba.
            $table->json('sportsdb_fanart_urls')->nullable()->after('sportsdb_fanart_url');

            // Vídeo: TheSportsDB devuelve una URL de YouTube por equipo.
            $table->string('sportsdb_youtube')->nullable()->after('sportsdb_fanart_urls');

            // Rutas locales. Las imágenes se DESCARGAN y se sirven desde
            // nuestro disco, no se enlazan a r2.thesportsdb.com: así no
            // dependemos de su disponibilidad, la CSP no necesita abrir un
            // dominio externo en img-src, y pasan por la misma validación
            // que el resto de imágenes de terceros.
            $table->string('banner_path')->nullable()->after('sportsdb_youtube');
            $table->string('fanart_path')->nullable()->after('banner_path');
            $table->timestamp('media_synced_at')->nullable()->after('fanart_path');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'sportsdb_fanart_urls', 'sportsdb_youtube',
                'banner_path', 'fanart_path', 'media_synced_at',
            ]);
        });
    }
};
