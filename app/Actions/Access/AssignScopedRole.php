<?php

declare(strict_types=1);

namespace App\Actions\Access;

use App\Exceptions\CannotAssignRole;
use App\Models\AuditLog;
use App\Models\Scope;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScopeResolver;
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
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly ViewerScopeResolver $scopes,
    ) {}

    public function handle(User $granter, User $subject, Role $role, Scope $scope): void
    {
        if (! $this->permissions->can($granter, 'roles.assign', $scope->path)) {
            throw new CannotAssignRole('You may not assign roles in this scope.');
        }

        $this->assertNoEscalation($granter, $role, $scope);

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

    private function assertNoEscalation(User $granter, Role $role, Scope $scope): void
    {
        if ($granter->is_super_admin) {
            return;
        }

        $granted = $role->permissions->pluck('name');

        $beyond = $granted->reject(
            fn (string $permission) => $this->permissions->can($granter, $permission, $scope->path)
        );

        if ($beyond->isNotEmpty()) {
            throw new CannotAssignRole(
                'You may not grant a role carrying permissions you do not hold here: '
                .$beyond->take(3)->implode(', ').'.'
            );
        }
    }
}
