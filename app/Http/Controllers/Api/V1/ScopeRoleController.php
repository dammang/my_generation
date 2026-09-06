<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Access\AssignScopedRole;
use App\Actions\Access\RevokeScopedRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AssignScopeRoleRequest;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Permissions\ScopeLocator;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Scoped role assignment: "you are an admin of the Guite clan."
 *
 * The escalation guard lives in the action, not here, so it applies equally to
 * Filament and to any future caller. The three read endpoints exist so a
 * committee screen can be built without a client having to guess any of it:
 * where you may appoint, who is appointed there, and who is eligible.
 */
class ScopeRoleController extends Controller
{
    public function __construct(
        private readonly ScopeLocator $scopes,
        private readonly PermissionResolver $permissions,
        private readonly AssignScopedRole $assign,
    ) {}

    /**
     * Who has been appointed at one scope.
     *
     * Direct grants only. Somebody who administers this clan because they run
     * the tribe above it is not listed, because they were not appointed here
     * and cannot be removed here — showing them beside a revoke button that
     * would silently do nothing is worse than not showing them.
     */
    public function index(Request $request): JsonResponse
    {
        $scope = $this->scopeFrom($request);
        $this->assertMayAppoint($request->user(), $scope);

        $rows = DB::table('scope_role_user as sru')
            ->join('roles as r', 'r.id', '=', 'sru.role_id')
            ->join('users as u', 'u.id', '=', 'sru.user_id')
            ->leftJoin('users as g', 'g.id', '=', 'sru.granted_by')
            ->where('sru.scope_id', $scope->getKey())
            ->orderBy('u.name')
            ->orderBy('r.name')
            ->get(['u.ulid as user_ulid', 'u.name as user_name', 'r.name as role', 'sru.granted_at', 'g.name as granted_by']);

        return ApiResponse::success($rows->map(fn ($row) => [
            'user' => ['ulid' => $row->user_ulid, 'name' => $row->user_name],
            'role' => $row->role,
            'granted_at' => $row->granted_at,
            'granted_by' => $row->granted_by,
        ])->all());
    }

    /**
     * Who could be appointed here.
     *
     * Active members of this scope *or of any scope above it*: memberships in
     * this archive are almost always held at the tribe, so a pool limited to
     * the clan's own members would be empty and the committee unappointable.
     */
    public function candidates(Request $request): JsonResponse
    {
        $scope = $this->scopeFrom($request);
        $this->assertMayAppoint($request->user(), $scope);

        $search = trim((string) $request->string('q'));

        $lineage = array_filter(explode('/', $scope->path), fn (string $part) => $part !== '');

        $held = DB::table('scope_role_user')
            ->join('roles', 'roles.id', '=', 'scope_role_user.role_id')
            ->where('scope_role_user.scope_id', $scope->getKey())
            ->get(['scope_role_user.user_id', 'roles.name'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $users = User::query()
            ->whereIn('id', Membership::query()
                ->whereIn('scope_id', $lineage)
                ->where('status', MembershipStatus::Active)
                ->select('user_id'))
            ->when($search !== '', fn ($q) => $q->where(
                fn ($q) => $q->where('name', 'like', $search.'%')->orWhere('email', $search),
            ))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'ulid', 'name']);

        return ApiResponse::success($users->map(fn (User $user) => [
            'user' => ['ulid' => $user->ulid, 'name' => $user->name],
            // What they already hold here, so the screen can show an existing
            // appointment rather than offering to make it a second time.
            'roles' => $held[$user->getKey()] ?? [],
        ])->all());
    }

    /**
     * The scopes this account may appoint to, and what it may hand out in each.
     *
     * Tribes and clans only: a family branch is a line of descent rather than
     * a body with a committee.
     */
    public function administered(Request $request): JsonResponse
    {
        $user = $request->user();
        $paths = $this->permissions->scopePathsFor($user, 'roles.assign');
        $everywhere = $user->is_super_admin
            || in_array('roles.assign', $this->permissions->globalPermissions($user), true);

        if (! $everywhere && $paths === []) {
            return ApiResponse::success([]);
        }

        $scopes = Scope::query()
            ->whereIn('scopeable_type', ['tribe', 'clan'])
            ->unless($everywhere, fn ($q) => $q->where(function ($q) use ($paths): void {
                foreach ($paths as $path) {
                    $q->orWhere('path', 'like', $path.'%');
                }
            }))
            ->with('scopeable:id,ulid,name')
            ->orderBy('depth')
            ->orderBy('id')
            ->limit(100)
            ->get();

        return ApiResponse::success($scopes
            ->filter(fn (Scope $scope) => $scope->scopeable !== null)
            ->map(fn (Scope $scope) => [
                'scope_type' => $scope->scopeable_type,
                'scope_ulid' => $scope->scopeable->ulid,
                'name' => $scope->scopeable->name,
                'depth' => $scope->depth,
                'assignable_roles' => $this->assign->assignableRoles($user, $scope),
            ])
            ->values()
            ->all());
    }

    public function store(AssignScopeRoleRequest $request, AssignScopedRole $action): JsonResponse
    {
        [$subject, $role, $scope] = $this->resolve($request->validated());

        $action->handle($request->user(), $subject, $role, $scope);

        return ApiResponse::success([
            'user_ulid' => $subject->ulid,
            'role' => $role->name,
            'scope_type' => $scope->scopeable_type,
            'scope_ulid' => $request->string('scope_ulid')->toString(),
        ]);
    }

    public function destroy(AssignScopeRoleRequest $request, RevokeScopedRole $action): JsonResponse
    {
        [$subject, $role, $scope] = $this->resolve($request->validated());

        $action->handle($request->user(), $subject, $role, $scope);

        return ApiResponse::noContent();
    }

    private function scopeFrom(Request $request): Scope
    {
        $validated = $request->validate([
            'scope_type' => ['required', Rule::in(['tribe', 'clan', 'family_branch'])],
            'scope_ulid' => ['required', 'string', 'size:26'],
            'q' => ['sometimes', 'string', 'max:80'],
        ]);

        return $this->scopes->locate($validated['scope_type'], $validated['scope_ulid']);
    }

    private function assertMayAppoint(User $user, Scope $scope): void
    {
        if (! $this->permissions->can($user, 'roles.assign', $scope->path)) {
            // Who runs a family is not public: an ordinary member asking gets
            // a refusal, not a roster.
            throw new AuthorizationException('You may not appoint anybody in this scope.');
        }
    }

    /**
     * @param  array<string, string>  $data
     * @return array{0: User, 1: Role, 2: Scope}
     */
    private function resolve(array $data): array
    {
        return [
            User::where('ulid', $data['user_ulid'])->firstOrFail(),
            Role::findByName($data['role'], 'web'),
            $this->scopes->locate($data['scope_type'], $data['scope_ulid']),
        ];
    }
}
