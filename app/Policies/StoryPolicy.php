<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Story;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for family narratives.
 */
class StoryPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Story $story): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'stories.create')
            || $this->permissions->scopePathsFor($user, 'stories.create') !== [];
    }

    public function update(User $user, Story $story): bool
    {
        return $this->permissions->can($user, 'stories.update', $this->scopePathFor($story));
    }

    public function delete(User $user, Story $story): bool
    {
        return $this->permissions->can($user, 'stories.delete', $this->scopePathFor($story));
    }

    public function verify(User $user, Story $story): bool
    {
        return $this->permissions->can($user, 'stories.verify', $this->scopePathFor($story));
    }
}
