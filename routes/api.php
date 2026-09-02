<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Versioned from the first endpoint. The Flutter client, a future web front
| end and any integration all consume this same surface — no genealogy logic
| is allowed to live in a client.
|
| Throttle buckets are defined in AppServiceProvider::configureRateLimiting().
*/

Route::prefix('v1')->as('api.v1.')->group(function (): void {

    // ── Public ───────────────────────────────────────────────────────────
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/forgot-password', [PasswordResetController::class, 'forgot'])->name('auth.forgot');
        Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])->name('auth.reset');
    });

    Route::get('health', fn () => ApiResponse::success([
        'status' => 'ok',
        'version' => 'v1',
        'time' => now()->toIso8601String(),
    ]))->name('health');

    // ── Authenticated ────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {

        Route::get('auth/me', [AuthController::class, 'me'])
            ->middleware('throttle:read')->name('auth.me');
        Route::patch('auth/profile', [AuthController::class, 'updateProfile'])
            ->middleware('throttle:write')->name('auth.profile');
        Route::post('auth/logout', [AuthController::class, 'logout'])
            ->middleware('throttle:write')->name('auth.logout');
        Route::post('auth/logout-everywhere', [AuthController::class, 'logoutEverywhere'])
            ->middleware('throttle:write')->name('auth.logout-everywhere');

        Route::middleware('throttle:read')->group(function (): void {
            Route::get('people', [PersonController::class, 'index'])->name('people.index');
            Route::get('people/{person}', [PersonController::class, 'show'])->name('people.show');
        });
    });
});
