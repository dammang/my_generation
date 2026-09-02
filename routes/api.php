<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClanController;
use App\Http\Controllers\Api\V1\FamilyBranchController;
use App\Http\Controllers\Api\V1\GenerationController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\ScopeRoleController;
use App\Http\Controllers\Api\V1\TribeController;
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

            // ── Organisation ─────────────────────────────────────────────
            Route::get('tribes', [TribeController::class, 'index'])->name('tribes.index');
            Route::get('tribes/{tribe}', [TribeController::class, 'show'])->name('tribes.show');
            Route::get('tribes/{tribe}/clans', [TribeController::class, 'clans'])->name('tribes.clans');
            Route::get('tribes/{tribe}/statistics', [TribeController::class, 'statistics'])->name('tribes.statistics');

            Route::get('clans', [ClanController::class, 'index'])->name('clans.index');
            Route::get('clans/{clan}', [ClanController::class, 'show'])->name('clans.show');
            Route::get('clans/{clan}/branches', [ClanController::class, 'branches'])->name('clans.branches');

            Route::get('family-branches', [FamilyBranchController::class, 'index'])->name('branches.index');
            Route::get('family-branches/{family_branch}', [FamilyBranchController::class, 'show'])->name('branches.show');

            Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');

            Route::get('places', [PlaceController::class, 'index'])->name('places.index');
            Route::get('places/{place}', [PlaceController::class, 'show'])->name('places.show');
            Route::get('places/{place}/children', [PlaceController::class, 'children'])->name('places.children');

            // ── Membership ───────────────────────────────────────────────
            Route::get('memberships', [MembershipController::class, 'index'])->name('memberships.index');
            Route::get('scope-members', [MembershipController::class, 'forScope'])->name('memberships.scope');
        });

        Route::middleware('throttle:write')->group(function (): void {
            Route::post('tribes', [TribeController::class, 'store'])->name('tribes.store');
            Route::patch('tribes/{tribe}', [TribeController::class, 'update'])->name('tribes.update');
            Route::delete('tribes/{tribe}', [TribeController::class, 'destroy'])->name('tribes.destroy');

            Route::post('clans', [ClanController::class, 'store'])->name('clans.store');
            Route::patch('clans/{clan}', [ClanController::class, 'update'])->name('clans.update');
            Route::delete('clans/{clan}', [ClanController::class, 'destroy'])->name('clans.destroy');

            Route::post('family-branches', [FamilyBranchController::class, 'store'])->name('branches.store');
            Route::patch('family-branches/{family_branch}', [FamilyBranchController::class, 'update'])->name('branches.update');
            Route::delete('family-branches/{family_branch}', [FamilyBranchController::class, 'destroy'])->name('branches.destroy');

            Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
            Route::patch('generations/{generation}', [GenerationController::class, 'update'])->name('generations.update');
            Route::delete('generations/{generation}', [GenerationController::class, 'destroy'])->name('generations.destroy');

            Route::post('places', [PlaceController::class, 'store'])->name('places.store');

            Route::post('memberships', [MembershipController::class, 'store'])->name('memberships.store');
            Route::post('memberships/{membership}/approve', [MembershipController::class, 'approve'])->name('memberships.approve');
            Route::post('memberships/{membership}/reject', [MembershipController::class, 'reject'])->name('memberships.reject');
            Route::delete('memberships/{membership}', [MembershipController::class, 'destroy'])->name('memberships.destroy');

            Route::post('scope-roles', [ScopeRoleController::class, 'store'])->name('scope-roles.store');
            Route::delete('scope-roles', [ScopeRoleController::class, 'destroy'])->name('scope-roles.destroy');
        });
    });
});
