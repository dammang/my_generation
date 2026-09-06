<?php

declare(strict_types=1);

namespace App\Actions\Access;

use App\Exceptions\CannotAssignRole;
use App\Models\AuditLog;
use App\Models\Scope;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScopeResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Grants a role to a user within one scope.
 *
 * Two guards, both necessary:
 *
 *   1. The granter must hold `roles.assign` at the target scope.
 *   2. The granter may not grant a permission they do not themselves hold
 *      there. Without this, a family admin with roles.assign could mint a
 *      tribe admin and escalate out of their own scope in one call.
 */
class AssignScopedRole
{
    /**
     * The roles that may ever be granted at a scope.
     *
     * super-admin is deliberately absent: it is a global bypass and no scoped
     * grant should be able to mint one. Kept here rather than in the form
     * request because the API, Filament and this action must agree on the
     * list, and a second copy of it would eventually be a different copy.
     *
     * @var list<string>
     */
    public const ASSIGNABLE = [
        'tribe-admin',
        'clan-admin',
        'family-admin',
        'historian',
        'contributor',
        'member',
        'viewer',
    ];

    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly ViewerScopeResolver $scopes,
    ) {}

    public function handle(User $granter, User $subject, Role $role, Scope $scope): void
    {
        if (! $this->permissions->can($granter, 'roles.assign', $scope->path)) {
            throw new CannotAssignRole('You may not assign roles in this scope.');
        }

        if (! $this->mayAssign($granter, $role, $scope)) {
            throw new CannotAssignRole(
                'You may not grant a role carrying permissions you do not hold here: '
                .$this->beyond($granter, $role, $scope)->take(3)->implode(', ').'.'
            );
        }

        DB::transaction(function () use ($granter, $subject, $role, $scope): void {
            DB::table('scope_role_user')->updateOrInsert(
                [
                    'user_id' => $subject->getKey(),
                    'role_id' => $role->getKey(),
                    'scope_id' => $scope->getKey(),
                ],
                [
                    'granted_by' => $granter->getKey(),
                    'granted_at' => now(),
                ],
            );

            AuditLog::create([
                'user_id' => $granter->getKey(),
                'action' => 'role.granted',
                'auditable_type' => $subject->getMorphClass(),
                'auditable_id' => $subject->getKey(),
                'context' => [
                    'role' => $role->name,
                    'scope_id' => $scope->getKey(),
                    'scope_path' => $scope->path,
                ],
            ]);
        });

        $this->scopes->forget($subject);
    }

    /**
     * Whether this granter may hand out this role here.
     *
     * Public because a client that offers a role it will then be refused is
     * worse than one that never offered it — the committee screen asks this
     * to decide what to put in its list.
     */
    public function mayAssign(User $granter, Role $role, Scope $scope): bool
    {
        if ($granter->is_super_admin) {
            return true;
        }

        return $this->beyond($granter, $role, $scope)->isEmpty();
    }

    /**
     * The roles this granter may hand out here, in ASSIGNABLE order.
     *
     * Empty when they hold no standing in the scope at all, so "nothing to
     * offer" and "may not appoint" are the same answer to a client.
     *
     * @return list<string>
     */
    public function assignableRoles(User $granter, Scope $scope): array
    {
        if (! $this->permissions->can($granter, 'roles.assign', $scope->path)) {
            return [];
        }

        return array_values(array_filter(
            self::ASSIGNABLE,
            function (string $name) use ($granter, $scope): bool {
                $role = Role::where('name', $name)->where('guard_name', 'web')->first();

                return $role !== null && $this->mayAssign($granter, $role, $scope);
            },
        ));
    }

    /**
     * The permissions the role carries that the granter does not hold here.
     *
     * @return Collection<int, string>
     */
    private function beyond(User $granter, Role $role, Scope $scope): Collection
    {
        return $role->permissions->pluck('name')->reject(
            fn (string $permission) => $this->permissions->can($granter, $permission, $scope->path)
        );
    }
}
