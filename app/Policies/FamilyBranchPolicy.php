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

    /**
     * Whether this account may create a branch anywhere at all.
     *
     * A scoped holder counts: `can()` with no path answers only for a global
     * permission, so asking it alone told every clan admin no — and a clan
     * admin who cannot create a family branch cannot record where their
     * generations are counted from, which is most of running a clan.
     *
     * Where they may create one is checked separately, against the tribe or
     * clan the branch is being placed in.
     */
    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'families.manage')
            || $this->permissions->scopePathsFor($user, 'families.manage') !== [];
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
