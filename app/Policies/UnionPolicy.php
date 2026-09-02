<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Union;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for marriages and partnerships.
 */
class UnionPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Union $union): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'unions.create')
            || $this->permissions->scopePathsFor($user, 'unions.create') !== [];
    }

    public function update(User $user, Union $union): bool
    {
        return $this->permissions->can($user, 'unions.update', $this->scopePathFor($union));
    }

    public function delete(User $user, Union $union): bool
    {
        return $this->permissions->can($user, 'unions.delete', $this->scopePathFor($union));
    }

    public function verify(User $user, Union $union): bool
    {
        return $this->permissions->can($user, 'unions.verify', $this->scopePathFor($union));
    }
}
