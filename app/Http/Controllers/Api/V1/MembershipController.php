<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Access\DecideMembership;
use App\Actions\Access\RequestMembership;
use App\Actions\Notifications\NotifyMembershipReviewers;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreMembershipRequest;
use App\Http\Resources\V1\MembershipResource;
use App\Models\Membership;
use App\Services\Permissions\PermissionResolver;
use App\Services\Permissions\ScopeLocator;
use App\Services\Privacy\ViewerScopeResolver;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Belonging, not capability. A membership widens what a user can see within a
 * tribe, clan or branch; it grants no permission to change anything.
 */
class MembershipController extends Controller
{
    public function __construct(
        private readonly ScopeLocator $scopes,
        private readonly PermissionResolver $permissions,
    ) {}

    /** The requester's own memberships. */
    public function index(Request $request): JsonResponse
    {
        $memberships = Membership::query()
            ->where('user_id', $request->user()->getKey())
            ->with('scope.scopeable')
            ->latest('id')
            ->get();

        return ApiResponse::success(MembershipResource::collection($memberships));
    }

    public function store(StoreMembershipRequest $request, RequestMembership $action): JsonResponse
    {
        $scope = $this->scopes->locate(
            $request->string('scope_type')->toString(),
            $request->string('scope_ulid')->toString(),
        );

        $membership = $action->handle($request->user(), $scope);

        if ($membership->status === MembershipStatus::Pending) {
            // Not fired when RequestMembership found the person already
            // active and returned early — there is nothing pending to review.
            app(NotifyMembershipReviewers::class)->handle($membership);
        }

        return ApiResponse::created(
            MembershipResource::make($membership->load('scope.scopeable'))
        );
    }

    /** Pending and active members of a scope. Visible only to its administrators. */
    public function forScope(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope_type' => ['required', 'string'],
            'scope_ulid' => ['required', 'string', 'size:26'],
            'status' => ['sometimes', 'string'],
        ]);

        $scope = $this->scopes->locate($validated['scope_type'], $validated['scope_ulid']);

        $this->assertAdministers($request, $scope->path);

        $memberships = Membership::query()
            ->where('scope_id', $scope->getKey())
            ->when(
                isset($validated['status']),
                fn ($q) => $q->where('status', $validated['status']),
            )
            ->with(['user:id,ulid,name', 'scope.scopeable'])
            ->orderBy('status')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(MembershipResource::collection($memberships));
    }

    public function approve(Request $request, Membership $membership, DecideMembership $action): JsonResponse
    {
        $membership->loadMissing('scope');
        $this->assertAdministers($request, $membership->scope->path);

        $action->handle($membership, MembershipStatus::Active, $request->user());

        return ApiResponse::success(MembershipResource::make($membership->load('scope.scopeable', 'user:id,ulid,name')));
    }

    public function reject(Request $request, Membership $membership, DecideMembership $action): JsonResponse
    {
        $membership->loadMissing('scope');
        $this->assertAdministers($request, $membership->scope->path);

        $action->handle($membership, MembershipStatus::Rejected, $request->user());

        return ApiResponse::success(MembershipResource::make($membership->load('scope.scopeable', 'user:id,ulid,name')));
    }

    /** Leaving is always the member's own decision, and needs no approval. */
    public function destroy(Request $request, Membership $membership): JsonResponse
    {
        if ($membership->user_id !== $request->user()->getKey()) {
            $this->assertAdministers($request, $membership->scope->path);
        }

        $membership->update(['status' => MembershipStatus::Left]);
        app(ViewerScopeResolver::class)->forget($membership->user);

        return ApiResponse::noContent();
    }

    private function assertAdministers(Request $request, string $scopePath): void
    {
        if (! $this->permissions->administersMembership($request->user(), $scopePath)) {
            // AuthorizationException, not a validation error: the request is
            // well formed, the caller simply has no standing here.
            throw new AuthorizationException('You do not administer this scope.');
        }
    }
}
