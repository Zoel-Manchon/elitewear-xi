<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fuerza la base de datos de test ANTES de que RefreshDatabase actúe.
     *
     * Esto no es una precaución teórica: los tests estaban ejecutándose contra
     * la base de datos real de desarrollo. phpunit.xml declara
     * DB_CONNECTION=sqlite y DB_DATABASE=:memory:, pero en Docker `env_file`
     * inyecta el .env como variables reales del sistema y PHPUnit no las
     * sobrescribe — el mismo motivo por el que APP_ENV nunca llegaba a valer
     * "testing" y el CSRF seguía activo.
     *
     * El síntoma era desconcertante: los tests del importador fallaban porque
     * el comando recorría los 154 equipos reales del catálogo y asignaba las
     * equipaciones de los fakes al primero que encontraba.
     *
     * refreshApplication() se ejecuta antes de setUpTraits(), que es donde
     * RefreshDatabase migra: aquí llegamos a tiempo.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        // Docker Compose carga el .env de desarrollo como variables reales del
        // proceso. PHPUnit no siempre puede reemplazarlas después de arrancar
        // Laravel, así que los tests fijan aquí toda la infraestructura volátil.
        // De este modo nunca dependen de APP_KEY, MySQL, Redis o la cola local.
        $this->app->detectEnvironment(static fn (): string => 'testing');

        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'app.key' => 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'mail.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);

        // Descarta servicios que pudieran haberse resuelto con el .env local.
        $this->app->forgetInstance('encrypter');
        DB::purge();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Sin consultas DNS reales: email:rfc,dns necesita red y los dominios
        // de ejemplo no tienen registro MX.
        config(['app.validate_email_dns' => false]);

        // Las pruebas de vistas no deben depender de public/build. El job
        // Dependencies ya ejecuta npm ci + npm run build de forma separada.
        $this->withoutVite();

        // Aunque el entorno se fuerza a testing arriba, se desactivan ambas
        // variantes explícitamente para que la suite no dependa del grupo web.
        //
        // Se desactivan las dos clases: PreventRequestForgery es la que está
        // realmente en el grupo `web` desde Laravel 13, y ValidateCsrfToken es
        // el nombre anterior, hoy deprecado y convertido en subclase suya.
        // Desactivar solo la segunda era un no-op silencioso.
        foreach ([PreventRequestForgery::class, ValidateCsrfToken::class] as $middleware) {
            if (class_exists($middleware)) {
                $this->withoutMiddleware($middleware);
            }
        }
    }
}
