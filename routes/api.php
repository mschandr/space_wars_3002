<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail']);

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    // Player management routes
    Route::prefix('players')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PlayerController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\PlayerController::class, 'store']);
        Route::get('{uuid}', [\App\Http\Controllers\Api\PlayerController::class, 'show']);
        Route::patch('{uuid}', [\App\Http\Controllers\Api\PlayerController::class, 'update']);
        Route::delete('{uuid}', [\App\Http\Controllers\Api\PlayerController::class, 'destroy']);
        Route::get('{uuid}/status', [\App\Http\Controllers\Api\PlayerController::class, 'status']);
        Route::get('{uuid}/stats', [\App\Http\Controllers\Api\PlayerController::class, 'stats']);
        Route::post('{uuid}/set-active', [\App\Http\Controllers\Api\PlayerController::class, 'setActive']);
    });

    // Ship management routes
    Route::prefix('ships')->group(function () {
        Route::get('{uuid}/status', [\App\Http\Controllers\Api\ShipController::class, 'status']);
        Route::get('{uuid}/fuel', [\App\Http\Controllers\Api\ShipController::class, 'fuel']);
        Route::post('{uuid}/regenerate-fuel', [\App\Http\Controllers\Api\ShipController::class, 'regenerateFuel']);
        Route::get('{uuid}/upgrades', [\App\Http\Controllers\Api\ShipController::class, 'upgrades']);
        Route::get('{uuid}/damage', [\App\Http\Controllers\Api\ShipController::class, 'damage']);
        Route::patch('{uuid}/name', [\App\Http\Controllers\Api\ShipController::class, 'rename']);
    });

    // Player's active ship endpoint
    Route::get('players/{playerUuid}/ship', [\App\Http\Controllers\Api\ShipController::class, 'getActiveShip']);

    // Navigation routes
    Route::prefix('players')->group(function () {
        Route::get('{uuid}/location', [\App\Http\Controllers\Api\NavigationController::class, 'getLocation']);
        Route::get('{uuid}/nearby-systems', [\App\Http\Controllers\Api\NavigationController::class, 'getNearbySystems']);
        Route::get('{uuid}/scan-local', [\App\Http\Controllers\Api\NavigationController::class, 'scanLocal']);
    });
});
