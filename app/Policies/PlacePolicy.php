<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Place;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for the gazetteer. Places are shared infrastructure: anyone
 * may propose one, but editing an existing place affects every record citing it.
 */
class PlacePolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Place $place): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Anyone who can contribute people can add the village they came from;
        // requiring an admin here would stall data entry for no safety gain.
        return $this->permissions->can($user, 'people.create')
            || $this->permissions->scopePathsFor($user, 'people.create') !== [];
    }

    // Places are not scoped to a tribe, so there is no path to prefix-match:
    // editing one requires the global permission.
    public function update(User $user, Place $place): bool
    {
        return $this->permissions->can($user, 'places.manage');
    }

    public function delete(User $user, Place $place): bool
    {
        return $this->permissions->can($user, 'places.manage');
    }
}
