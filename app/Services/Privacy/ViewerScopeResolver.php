<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\MembershipStatus;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds a ViewerScope once per request.
 *
 * Cached for ten minutes and busted on membership or role change. The cost of a
 * stale scope is bounded: it can only reflect entitlements the user genuinely
 * had minutes ago, and every privacy decision is still made server-side against
 * whatever scope is current.
 */
class ViewerScopeResolver
{
    private const CACHE_TTL = 600;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function resolve(?User $user): ViewerScope
    {
        if ($user === null) {
            return ViewerScope::guest();
        }

        $key = "viewer:scope:{$user->getKey()}";

        $cached = Cache::get($key);

        if (is_array($cached) && ($scope = ViewerScope::fromArray($cached)) !== null) {
            return $scope;
        }

        $scope = $this->build($user);

        Cache::put($key, $scope->toArray(), self::CACHE_TTL);

        return $scope;
    }

    public function forget(User $user): void
    {
        Cache::forget("viewer:scope:{$user->getKey()}");
        $this->permissions->forget($user);
    }

    private function build(User $user): ViewerScope
    {
        $memberships = DB::table('memberships as m')
            ->join('scopes as s', 's.id', '=', 'm.scope_id')
            ->where('m.user_id', $user->getKey())
            ->where('m.status', MembershipStatus::Active->value)
            ->get(['s.scopeable_type as type', 's.scopeable_id as id']);

        $byType = fn (string $type) => $memberships
            ->where('type', $type)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $adminPaths = $this->permissions->adminScopePaths($user);
        $administered = $this->expandAdminScopes($adminPaths);

        return new ViewerScope(
            userId: $user->getKey(),
            personId: $user->person_id,
            tribeIds: $byType('tribe'),
            clanIds: $byType('clan'),
            branchIds: $byType('family_branch'),
            adminScopePaths: $adminPaths,
            adminTribeIds: $administered['tribe'] ?? [],
            adminClanIds: $administered['clan'] ?? [],
            adminBranchIds: $administered['family_branch'] ?? [],
            kinPersonIds: $this->kinOf($user->person_id),
            permissions: $this->permissions->globalPermissions($user),
            isSuperAdmin: (bool) $user->is_super_admin,
        );
    }

    /**
     * Turns administered scope *paths* into the tribe, clan and branch ids
     * beneath them.
     *
     * Prefix matching is the right model for authorization, but a privacy
     * predicate has to run inside a SQL WHERE clause against people.tribe_id
     * and friends. Expanding once per request keeps the query simple and the
     * index usable.
     *
     * @param  array<int, string>  $adminPaths
     * @return array<string, array<int, int>>
     */
    private function expandAdminScopes(array $adminPaths): array
    {
        if ($adminPaths === []) {
            return [];
        }

        $query = DB::table('scopes');

        foreach ($adminPaths as $path) {
            $query->orWhere('path', 'like', $path.'%');
        }

        $grouped = [];

        foreach ($query->get(['scopeable_type', 'scopeable_id']) as $row) {
            $grouped[$row->scopeable_type][] = (int) $row->scopeable_id;
        }

        return array_map(
            static fn (array $ids) => array_values(array_unique($ids)),
            $grouped,
        );
    }

    /**
     * Close kin of the viewer's own claimed person: two generations up, two
     * down, plus spouses and siblings.
     *
     * This is what makes "family" a *relational* scope rather than merely a
     * branch label — an uncle who was never assigned to the right family branch
     * still sees his nephew. Bounded by config so a well-connected person in a
     * large clan cannot blow up the request.
     *
     * @return array<int, int>
     */
    private function kinOf(?int $personId): array
    {
        if ($personId === null) {
            return [];
        }

        $up = (int) config('genealogy.privacy.kin_generations_up');
        $down = (int) config('genealogy.privacy.kin_generations_down');
        $cap = (int) config('genealogy.privacy.kin_max_people');

        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE up (person_id, depth) AS (
                SELECT ?, 0
                UNION ALL
                SELECT fe.parent_id, u.depth + 1
                FROM up u JOIN family_edges fe ON fe.child_id = u.person_id
                WHERE u.depth < ?
            ),
            down (person_id, depth) AS (
                SELECT ?, 0
                UNION ALL
                SELECT fe.child_id, d.depth + 1
                FROM down d JOIN family_edges fe ON fe.parent_id = d.person_id
                WHERE d.depth < ?
            ),
            bloodline AS (
                SELECT person_id FROM up
                UNION
                SELECT person_id FROM down
            ),
            -- Everyone descending from an ancestor within reach: siblings,
            -- nieces and nephews, first cousins.
            collateral AS (
                SELECT fe.child_id AS person_id
                FROM bloodline b JOIN family_edges fe ON fe.parent_id = b.person_id
            ),
            spouses AS (
                SELECT CASE WHEN u.partner_1_id = b.person_id THEN u.partner_2_id
                            ELSE u.partner_1_id END AS person_id
                FROM bloodline b
                JOIN unions u ON (u.partner_1_id = b.person_id OR u.partner_2_id = b.person_id)
                WHERE u.deleted_at IS NULL
            )
            SELECT DISTINCT person_id FROM (
                SELECT person_id FROM bloodline
                UNION SELECT person_id FROM collateral
                UNION SELECT person_id FROM spouses
            ) kin
            WHERE person_id IS NOT NULL
            LIMIT ?
        SQL, [$personId, $up, $personId, $down, $cap]);

        return array_map(static fn ($row) => (int) $row->person_id, $rows);
    }
}
