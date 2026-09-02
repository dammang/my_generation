<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Source;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for evidence records.
 */
class SourcePolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Source $source): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'sources.create')
            || $this->permissions->scopePathsFor($user, 'sources.create') !== [];
    }

    public function update(User $user, Source $source): bool
    {
        return $this->permissions->can($user, 'sources.update', $this->scopePathFor($source));
    }

    public function delete(User $user, Source $source): bool
    {
        return $this->permissions->can($user, 'sources.delete', $this->scopePathFor($source));
    }

    public function verify(User $user, Source $source): bool
    {
        return $this->permissions->can($user, 'sources.verify', $this->scopePathFor($source));
    }
}
