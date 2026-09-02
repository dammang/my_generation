<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyBranch;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for family branches.
 */
class FamilyBranchPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, FamilyBranch $branch): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'families.manage');
    }

    public function update(User $user, FamilyBranch $branch): bool
    {
        return $this->permissions->can($user, 'families.manage', $this->scopePathFor($branch));
    }

    public function delete(User $user, FamilyBranch $branch): bool
    {
        return $this->permissions->can($user, 'families.manage', $this->scopePathFor($branch));
    }
}
