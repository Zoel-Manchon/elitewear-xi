<?php

use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Versionada desde el primer día: /api/v1 permite publicar una v2 sin romper
| a quien ya consume la v1. Añadir la versión después es mucho más caro.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // --- Público -----------------------------------------------------------
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    });

    // --- Emisión de tokens (limitada aparte, es superficie de fuerza bruta) --
    Route::post('/tokens', [TokenController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('tokens.store');

    // --- Privado -----------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::delete('/tokens', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::middleware('abilities:orders:read')->group(function () {
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        });
    });
});
