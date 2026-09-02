<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PersonEvent;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for chronicle entries.
 */
class PersonEventPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, PersonEvent $event): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'events.create')
            || $this->permissions->scopePathsFor($user, 'events.create') !== [];
    }

    public function update(User $user, PersonEvent $event): bool
    {
        return $this->permissions->can($user, 'events.update', $this->scopePathFor($event));
    }

    public function delete(User $user, PersonEvent $event): bool
    {
        return $this->permissions->can($user, 'events.delete', $this->scopePathFor($event));
    }

    public function verify(User $user, PersonEvent $event): bool
    {
        return $this->permissions->can($user, 'events.verify', $this->scopePathFor($event));
    }
}
