<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Models\Scope;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Merges global roles with scoped ones.
 *
 * Global roles use Spatie's ordinary model_has_roles. Scoped roles live in
 * scope_role_user and grant a role *within* one tribe, clan or family branch.
 *
 * Authority flows downward by prefix-matching the materialised scopes.path, so
 * a Tribe Admin automatically administers every clan and branch beneath
 * without a row per clan and without a recursive query on every check.
 */
class PermissionResolver
{
    private const CACHE_TTL = 600;

    /** Every permission the user holds anywhere, for the ViewerScope. */
    public function globalPermissions(User $user): array
    {
        return Cache::remember(
            "permissions:global:{$user->getKey()}",
            self::CACHE_TTL,
            fn () => $user->getAllPermissions()->pluck('name')->all(),
        );
    }

    /**
     * Scope paths where the user holds a role carrying the given permission.
     *
     * @return array<int, string>
     */
    public function scopePathsFor(User $user, string $permission): array
    {
        return $this->scopedGrants($user)[$permission] ?? [];
    }

    /** Scope paths where the user holds any administrative permission. */
    public function adminScopePaths(User $user): array
    {
        $grants = $this->scopedGrants($user);

        $paths = [];

        foreach ($grants as $permission => $scopePaths) {
            if (str_ends_with($permission, '.manage')
                || str_ends_with($permission, '.verify')
                || str_ends_with($permission, '.approve')) {
                $paths = [...$paths, ...$scopePaths];
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Can this user do `$permission` on a record living at `$scopePath`?
     *
     * A null path means the record is not scoped to a tribe at all — only a
     * super admin or a holder of the global permission may act on it.
     */
    public function can(?User $user, string $permission, ?string $scopePath = null): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        if (in_array($permission, $this->globalPermissions($user), true)) {
            return true;
        }

        if ($scopePath === null) {
            return false;
        }

        foreach ($this->scopePathsFor($user, $permission) as $grantedPath) {
            if (str_starts_with($scopePath, $grantedPath)) {
                return true;
            }
        }

        return false;
    }

    public function forget(User $user): void
    {
        Cache::forget("permissions:global:{$user->getKey()}");
        Cache::forget("permissions:scoped:{$user->getKey()}");
    }

    /**
     * permission name => scope paths the user holds it in.
     *
     * Loaded in one query and cached, because a single request may authorise
     * hundreds of records and must not re-derive this per record.
     *
     * @return array<string, array<int, string>>
     */
    private function scopedGrants(User $user): array
    {
        return Cache::remember(
            "permissions:scoped:{$user->getKey()}",
            self::CACHE_TTL,
            function () use ($user): array {
                $rows = DB::table('scope_role_user as sru')
                    ->join('scopes as s', 's.id', '=', 'sru.scope_id')
                    ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'sru.role_id')
                    ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                    ->where('sru.user_id', $user->getKey())
                    ->get(['p.name as permission', 's.path as path']);

                $grants = [];

                foreach ($rows as $row) {
                    $grants[$row->permission][] = $row->path;
                }

                return array_map(
                    fn (array $paths) => array_values(array_unique($paths)),
                    $grants,
                );
            },
        );
    }
}
