<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clan;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;

/**
 * Authorization for clans and sub-clans.
 */
class ClanPolicy
{
    use ResolvesScopePath;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Clan $clan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'clans.manage');
    }

    public function update(User $user, Clan $clan): bool
    {
        return $this->permissions->can($user, 'clans.manage', $this->scopePathFor($clan));
    }

    public function delete(User $user, Clan $clan): bool
    {
        return $this->permissions->can($user, 'clans.manage', $this->scopePathFor($clan));
    }
}
