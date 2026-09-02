<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Relationship;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for parentage and guardianship edges. Scoped to the child's
 * placement, since that is whose tree the edge appears in.
 */
class RelationshipPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Relationship $relationship): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'relationships.create')
            || $this->permissions->scopePathsFor($user, 'relationships.create') !== [];
    }

    public function update(User $user, Relationship $relationship): bool
    {
        return $this->permissions->can($user, 'relationships.update', $this->scopePathFor($relationship));
    }

    public function delete(User $user, Relationship $relationship): bool
    {
        return $this->permissions->can($user, 'relationships.delete', $this->scopePathFor($relationship));
    }

    public function verify(User $user, Relationship $relationship): bool
    {
        return $this->permissions->can($user, 'relationships.verify', $this->scopePathFor($relationship));
    }
}
