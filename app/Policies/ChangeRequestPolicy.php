<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Illuminate\Support\Facades\DB;

/**
 * Who may see and decide a proposal.
 *
 * Reviewing is scoped: a clan's reviewer approves changes in their clan, not
 * across the tribe. The requester can always see their own proposal — being
 * unable to check what happened to your own submission is how a contributor
 * decides the app swallowed it.
 */
class ChangeRequestPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChangeRequest $request): bool
    {
        return $request->requested_by === $user->getKey()
            || $this->canReview($user, $request);
    }

    public function review(User $user, ChangeRequest $request): bool
    {
        return $this->canReview($user, $request);
    }

    /**
     * Withdrawing is the requester's own; a reviewer rejects instead.
     *
     * Only while pending — withdrawing a decided proposal would rewrite the
     * record of what was decided.
     */
    public function withdraw(User $user, ChangeRequest $request): bool
    {
        return $request->requested_by === $user->getKey()
            && $request->status->value === 'pending';
    }

    private function canReview(User $user, ChangeRequest $request): bool
    {
        $path = $request->scope_id === null
            ? null
            : DB::table('scopes')->where('id', $request->scope_id)->value('path');

        return $this->permissions->can($user, 'changes.approve', $path);
    }
}
