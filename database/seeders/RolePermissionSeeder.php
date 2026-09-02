<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions.
 *
 * Global roles are attached to users through Spatie's ordinary model_has_roles.
 * The same role rows are ALSO used by scope_role_user, which grants a role
 * within one tribe, clan or family branch. PermissionResolver (Phase 4) merges
 * both, and authority flows downward by prefix-matching scopes.path — so a
 * Tribe Admin needs no row per clan.
 *
 * Idempotent: safe to re-run after adding a permission.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const PERMISSIONS = [
        'people' => ['view', 'create', 'update', 'delete', 'verify', 'merge'],
        'relationships' => ['create', 'update', 'delete', 'verify'],
        'unions' => ['create', 'update', 'delete', 'verify'],
        'events' => ['create', 'update', 'delete', 'verify'],
        'stories' => ['create', 'update', 'delete', 'verify'],
        'sources' => ['create', 'update', 'delete', 'verify'],
        'media' => ['upload', 'delete'],
        'tribes' => ['manage'],
        'clans' => ['manage'],
        'families' => ['manage'],
        'generations' => ['manage'],
        'places' => ['manage'],
        'changes' => ['review', 'approve'],
        'disputes' => ['resolve'],
        'duplicates' => ['review'],
        'claims' => ['approve'],
        'users' => ['manage'],
        'roles' => ['assign'],
    ];

    /** @var array<string, list<string>> role => permissions, or ['*'] for all. */
    private const ROLES = [
        'super-admin' => ['*'],

        'tribe-admin' => [
            'people.*', 'relationships.*', 'unions.*', 'events.*', 'stories.*', 'sources.*',
            'media.*', 'clans.manage', 'families.manage', 'generations.manage', 'places.manage',
            'changes.*', 'disputes.resolve', 'duplicates.review', 'claims.approve', 'roles.assign',
        ],

        'clan-admin' => [
            'people.*', 'relationships.*', 'unions.*', 'events.*', 'stories.*', 'sources.*',
            'media.*', 'families.manage', 'changes.*', 'disputes.resolve', 'duplicates.review',
            'claims.approve',
        ],

        'family-admin' => [
            'people.view', 'people.create', 'people.update', 'people.verify',
            'relationships.create', 'relationships.update', 'relationships.verify',
            'unions.create', 'unions.update', 'unions.verify',
            'events.create', 'events.update', 'events.verify',
            'stories.create', 'stories.update', 'stories.verify',
            'sources.create', 'sources.update', 'sources.verify',
            'media.upload', 'changes.review', 'changes.approve', 'claims.approve',
        ],

        // Verifies facts and settles disputes, but manages no members.
        'historian' => [
            'people.view', 'people.update', 'people.verify', 'people.merge',
            'relationships.update', 'relationships.verify',
            'unions.update', 'unions.verify',
            'events.update', 'events.verify',
            'stories.verify', 'sources.create', 'sources.update', 'sources.verify',
            'changes.review', 'changes.approve', 'disputes.resolve', 'duplicates.review',
        ],

        'contributor' => [
            'people.view', 'people.create', 'people.update',
            'relationships.create', 'relationships.update',
            'unions.create', 'unions.update',
            'events.create', 'events.update',
            'stories.create', 'stories.update',
            'sources.create', 'media.upload',
        ],

        'member' => ['people.view'],

        // Public and share-link content only. Holds no permissions by design.
        'viewer' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];
        foreach (self::PERMISSIONS as $group => $actions) {
            foreach ($actions as $action) {
                $name = "{$group}.{$action}";
                $all[] = $name;
                Permission::findOrCreate($name, 'web');
            }
        }

        foreach (self::ROLES as $roleName => $patterns) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($this->expand($patterns, $all));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(
            sprintf('Seeded %d permissions across %d roles.', count($all), count(self::ROLES))
        );
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $all
     * @return list<string>
     */
    private function expand(array $patterns, array $all): array
    {
        if ($patterns === ['*']) {
            return $all;
        }

        $resolved = [];
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                $resolved = array_merge($resolved, array_filter(
                    $all,
                    fn (string $p) => str_starts_with($p, $prefix)
                ));
            } else {
                $resolved[] = $pattern;
            }
        }

        return array_values(array_unique($resolved));
    }
}
