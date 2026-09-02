<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for tribes. Only tribe administrators and above may alter
 * the organisational spine, because every scoped permission hangs off it.
 */
class TribePolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Tribe $tribe): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'tribes.manage');
    }

    public function update(User $user, Tribe $tribe): bool
    {
        return $this->permissions->can($user, 'tribes.manage', $this->scopePathFor($tribe));
    }

    public function delete(User $user, Tribe $tribe): bool
    {
        return $this->permissions->can($user, 'tribes.manage', $this->scopePathFor($tribe));
    }
}
