<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChangeRequestController;
use App\Http\Controllers\Api\V1\ClanController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\FamilyBranchController;
use App\Http\Controllers\Api\V1\GenerationController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\PersonEventController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\ProfileClaimController;
use App\Http\Controllers\Api\V1\RelationshipController;
use App\Http\Controllers\Api\V1\RevisionController;
use App\Http\Controllers\Api\V1\ScopeRoleController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TreeController;
use App\Http\Controllers\Api\V1\TribeController;
use App\Http\Controllers\Api\V1\UnionController;
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

        // Google, Apple and email/password all arrive here as a verified
        // Firebase identity and leave with a Sanctum token.
        Route::post('auth/firebase', [AuthController::class, 'firebase'])->name('auth.firebase');
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

        // Throttled hard: this is the one endpoint that makes us send mail to
        // an address on demand.
        Route::post('auth/email/resend', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:6,1')->name('auth.email.resend');
        Route::post('auth/logout', [AuthController::class, 'logout'])
            ->middleware('throttle:write')->name('auth.logout');
        Route::post('auth/logout-everywhere', [AuthController::class, 'logoutEverywhere'])
            ->middleware('throttle:write')->name('auth.logout-everywhere');

        Route::middleware('throttle:read')->group(function (): void {
            Route::get('people', [PersonController::class, 'index'])->name('people.index');
            Route::get('people/{person}', [PersonController::class, 'show'])->name('people.show');
            Route::get('people/{person}/family', [PersonController::class, 'family'])->name('people.family');
            Route::get('people/{person}/names', [PersonController::class, 'names'])->name('people.names');
            Route::get('people/{person}/timeline', [PersonEventController::class, 'timeline'])->name('people.timeline');
            Route::get('people/{person}/revisions', [RevisionController::class, 'forPerson'])->name('people.revisions');
            Route::get('people/{person}/disputes', [DisputeController::class, 'forPerson'])->name('people.disputes');
            Route::post('people/{person}/verify', [PersonController::class, 'verify'])->name('people.verify');

            // Review queue, both sides of it.
            Route::get('change-requests', [ChangeRequestController::class, 'index'])->name('changes.index');
            Route::get('change-requests/{change_request}', [ChangeRequestController::class, 'show'])->name('changes.show');
            Route::post('change-requests/{change_request}/approve', [ChangeRequestController::class, 'approve'])->name('changes.approve');
            Route::post('change-requests/{change_request}/reject', [ChangeRequestController::class, 'reject'])->name('changes.reject');
            Route::post('change-requests/{change_request}/withdraw', [ChangeRequestController::class, 'withdraw'])->name('changes.withdraw');

            // Drains a phone's offline queue in one round trip. Each operation
            // carries its own id, so replaying the batch is safe.
            Route::post('sync/batch', [SyncController::class, 'batch'])->name('sync.batch');

            // Where to send a push, and where to stop sending one.
            Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
            Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');

            Route::post('disputes', [DisputeController::class, 'store'])->name('disputes.store');
            Route::post('disputes/{dispute}/resolve', [DisputeController::class, 'resolve'])->name('disputes.resolve');
            Route::get('event-types', [PersonEventController::class, 'types'])->name('event-types.index');

            // Stories are scoped by Story::visibleTo, not by this route — a
            // guest listing them gets the public ones and nothing else.
            Route::get('people/{person}/media', [MediaController::class, 'forPerson'])->name('people.media');
            Route::get('stories', [StoryController::class, 'index'])->name('stories.index');
            Route::get('stories/{story}', [StoryController::class, 'show'])->name('stories.show');
            Route::get('unions/{union}', [UnionController::class, 'show'])->name('unions.show');

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
            Route::get('profile-claims', [ProfileClaimController::class, 'index'])->name('claims.index');
            Route::get('scope-members', [MembershipController::class, 'forScope'])->name('memberships.scope');
        });

        // Tree traversal runs recursive CTEs, so it gets its own tighter
        // bucket: a client looping over expansions must not starve plain reads.
        Route::middleware('throttle:tree')->group(function (): void {
            Route::get('tree/{person}', [TreeController::class, 'show'])->name('tree.show');
            Route::get('tree/{person}/ancestors', [TreeController::class, 'ancestors'])->name('tree.ancestors');
            Route::get('tree/{person}/descendants', [TreeController::class, 'descendants'])->name('tree.descendants');
            Route::get('tree/{person}/lineage', [TreeController::class, 'lineage'])->name('tree.lineage');
            Route::get('tree/{person}/path-to/{other}', [TreeController::class, 'pathTo'])->name('tree.path');
        });

        // Every write is replay-safe when the client sends an operation id,
        // and every write requires a confirmed address.
        //
        // Account management deliberately sits outside this group: somebody
        // who mistyped their address has to be able to correct it and ask for
        // a new link, which they could not do if fixing it were itself gated
        // on having fixed it.
        //
        // The check runs before `idempotent` on purpose: that middleware claims
        // the client's operation id before passing the request on, and a write
        // that was never allowed to happen must not burn the id it was sent
        // with — the retry after verifying would replay a refusal.
        Route::middleware(['throttle:write', 'verified.email', 'idempotent'])->group(function (): void {
            // ── Genealogy ────────────────────────────────────────────────
            Route::post('people', [PersonController::class, 'store'])->name('people.store');
            Route::patch('people/{person}', [PersonController::class, 'update'])->name('people.update');
            Route::delete('people/{person}', [PersonController::class, 'destroy'])->name('people.destroy');

            // The guided flow: "Add Son" becomes a person, a union, two parent
            // edges and a birth-order row, in one transaction.
            Route::post('people/{person}/relatives', [PersonController::class, 'addRelative'])->name('people.relatives');

            Route::post('people/{person}/names', [PersonController::class, 'storeName'])->name('people.names.store');
            Route::delete('people/{person}/names/{person_name}', [PersonController::class, 'destroyName'])->name('people.names.destroy');

            Route::post('media', [MediaController::class, 'store'])->name('media.store');
            Route::post('stories', [StoryController::class, 'store'])->name('stories.store');

            Route::post('person-events', [PersonEventController::class, 'store'])->name('events.store');
            Route::patch('person-events/{person_event}', [PersonEventController::class, 'update'])->name('events.update');
            Route::delete('person-events/{person_event}', [PersonEventController::class, 'destroy'])->name('events.destroy');

            Route::post('relationships', [RelationshipController::class, 'store'])->name('relationships.store');
            Route::patch('relationships/{relationship}', [RelationshipController::class, 'update'])->name('relationships.update');
            Route::delete('relationships/{relationship}', [RelationshipController::class, 'destroy'])->name('relationships.destroy');

            Route::post('unions', [UnionController::class, 'store'])->name('unions.store');
            Route::patch('unions/{union}', [UnionController::class, 'update'])->name('unions.update');
            Route::delete('unions/{union}', [UnionController::class, 'destroy'])->name('unions.destroy');
            Route::post('unions/{union}/children', [UnionController::class, 'addChild'])->name('unions.children.store');
            Route::delete('unions/{union}/children/{person}', [UnionController::class, 'removeChild'])->name('unions.children.destroy');

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

            Route::post('profile-claims', [ProfileClaimController::class, 'store'])->name('claims.store');
            Route::post('profile-claims/{profile_claim}/approve', [ProfileClaimController::class, 'approve'])->name('claims.approve');
            Route::post('profile-claims/{profile_claim}/reject', [ProfileClaimController::class, 'reject'])->name('claims.reject');
            Route::delete('profile-claims/{profile_claim}', [ProfileClaimController::class, 'destroy'])->name('claims.destroy');

            Route::post('scope-roles', [ScopeRoleController::class, 'store'])->name('scope-roles.store');
            Route::delete('scope-roles', [ScopeRoleController::class, 'destroy'])->name('scope-roles.destroy');
        });
    });
});
