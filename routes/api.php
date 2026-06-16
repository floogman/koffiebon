<?php

use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\Pwa\PwaController;
use App\Http\Controllers\Api\Staff\DashboardController;
use App\Http\Controllers\Api\Staff\StaffAuthController;
use App\Http\Controllers\Api\Staff\StaffController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Klant / PWA — passwordless auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [CustomerAuthController::class, 'register'])
        ->middleware('throttle:6,1');
    Route::post('magic-link', [CustomerAuthController::class, 'magicLink'])
        ->middleware('throttle:6,1');
    Route::post('claim', [CustomerAuthController::class, 'claim'])
        ->middleware('throttle:20,1');

    Route::get('verify/{customer}', [CustomerAuthController::class, 'verify'])
        ->middleware('signed')
        ->name('api.auth.verify');
});

/*
|--------------------------------------------------------------------------
| Klant / PWA — geauthenticeerd met device-token (ability: customer)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'abilities:customer'])->prefix('pwa')->group(function () {
    Route::get('me', [PwaController::class, 'me']);
    Route::get('drinks', [PwaController::class, 'drinks']);
    Route::get('cards/{card}', [PwaController::class, 'card']);
    Route::post('tokens', [PwaController::class, 'issueToken'])->middleware('throttle:60,1');
});

// Kanaal-autorisatie voor de PWA-websocket. De default /broadcasting/auth-route
// leunt op cookies; de PWA werkt met bearer-tokens, dus hier een eigen endpoint
// onder Sanctum. Echo wijst zijn authEndpoint naar /api/broadcasting/auth.
Route::post('broadcasting/auth', fn () => Broadcast::auth(request()))
    ->middleware(['auth:sanctum', 'abilities:customer']);

/*
|--------------------------------------------------------------------------
| Staff / balie
|--------------------------------------------------------------------------
*/
Route::prefix('staff')->group(function () {
    Route::post('login', [StaffAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'abilities:staff'])->group(function () {
        Route::post('logout', [StaffAuthController::class, 'logout']);
        Route::get('products', [StaffController::class, 'products']);
        Route::get('drinks', [StaffController::class, 'drinks']);
        Route::post('scan', [StaffController::class, 'scan'])->middleware('throttle:120,1');
        Route::post('cards', [StaffController::class, 'createCard']);
        Route::post('cards/{card}/activate', [StaffController::class, 'activateCard']);

        // Merchant-dashboard (admin only, gecontroleerd in de controller).
        Route::get('dashboard', [DashboardController::class, 'show']);
    });
});
