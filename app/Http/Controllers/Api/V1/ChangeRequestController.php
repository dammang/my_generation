<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Verification\ApplyChangeRequest;
use App\Enums\ChangeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The review queue, from both sides.
 *
 * A contributor watches their own proposals; a reviewer works a queue scoped to
 * what they are entitled to decide. Both are the same list filtered differently,
 * because they are the same records.
 */
class ChangeRequestController extends Controller
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    /**
     * `?filter=mine` — what I proposed.
     * `?filter=review` — what I may decide (the default for a reviewer).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['sometimes', 'in:mine,review'],
            'status' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $filter = $data['filter'] ?? 'mine';

        $query = ChangeRequest::query()
            ->with(['requester:id,ulid,name', 'decider:id,ulid,name', 'target'])
            ->latest('id');

        if ($filter === 'mine') {
            $query->where('requested_by', $user->getKey());
        } else {
            $this->scopeToReviewable($query, $user);
        }

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        } elseif ($filter === 'review') {
            // A queue defaults to what still needs doing.
            $query->where('status', ChangeRequestStatus::Pending);
        }

        $page = $query->paginate($data['per_page'] ?? 20);

        return ApiResponse::success(
            ChangeRequestResource::collection($page),
            meta: ['filter' => $filter, 'can_review' => $this->canReviewAnything($user)],
        );
    }

    public function show(ChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('view', $changeRequest);

        return ApiResponse::success(
            ChangeRequestResource::make(
                $changeRequest->load(['requester:id,ulid,name', 'decider:id,ulid,name', 'target', 'reviews'])
            )
        );
    }

    public function approve(
        Request $request,
        ChangeRequest $changeRequest,
        ApplyChangeRequest $apply,
    ): JsonResponse {
        $this->authorize('review', $changeRequest);

        $data = $request->validate(['comment' => ['sometimes', 'nullable', 'string', 'max:1000']]);

        // A superseded record throws, and the exception carries the three-way
        // diff so the reviewer can decide rather than being told "conflict".
        $apply->handle($changeRequest, $request->user(), $data['comment'] ?? null);

        return ApiResponse::success(
            ChangeRequestResource::make(
                $changeRequest->fresh(['requester:id,ulid,name', 'decider:id,ulid,name', 'target', 'reviews'])
            )
        );
    }

    public function reject(
        Request $request,
        ChangeRequest $changeRequest,
        ApplyChangeRequest $apply,
    ): JsonResponse {
        $this->authorize('review', $changeRequest);

        $data = $request->validate(['comment' => ['sometimes', 'nullable', 'string', 'max:1000']]);

        $apply->reject($changeRequest, $request->user(), $data['comment'] ?? null);

        return ApiResponse::success(
            ChangeRequestResource::make(
                $changeRequest->fresh(['requester:id,ulid,name', 'decider:id,ulid,name', 'target', 'reviews'])
            )
        );
    }

    /** The requester's own retraction. A reviewer rejects instead. */
    public function withdraw(ChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('withdraw', $changeRequest);

        $changeRequest->forceFill(['status' => ChangeRequestStatus::Withdrawn])->save();

        return ApiResponse::success(ChangeRequestResource::make($changeRequest));
    }

    /**
     * Limits the queue to scopes where this reviewer holds `changes.approve`.
     *
     * Scope paths are materialised (`/1/14/57/`), so authority over a branch is
     * a prefix match rather than a recursive walk.
     */
    private function scopeToReviewable(Builder $query, User $user): void
    {
        // A tribe-wide grant is not tied to any one scope path, so filtering by
        // path would hide everything from exactly the people meant to see it.
        if ($user->is_super_admin || $this->permissions->can($user, 'changes.approve')) {
            return;
        }

        $paths = $this->permissions->scopePathsFor($user, 'changes.approve');

        if ($paths === []) {
            // Not "everything" — nothing. An empty allow-list that falls
            // through to an unfiltered query is how a queue leaks.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('scope_id', function ($sub) use ($paths) {
            $sub->select('id')->from('scopes');

            $sub->where(function ($where) use ($paths) {
                foreach ($paths as $path) {
                    $where->orWhere('path', 'like', $path.'%');
                }
            });
        });
    }

    /** Whether to offer the review queue at all. */
    private function canReviewAnything(User $user): bool
    {
        return $user->is_super_admin
            || $this->permissions->can($user, 'changes.approve')
            || $this->permissions->scopePathsFor($user, 'changes.approve') !== [];
    }
}
