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

class RevokeScopedRole
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly ViewerScopeResolver $scopes,
    ) {}

    public function handle(User $revoker, User $subject, Role $role, Scope $scope): void
    {
        if (! $this->permissions->can($revoker, 'roles.assign', $scope->path)) {
            throw new CannotAssignRole('You may not change roles in this scope.');
        }

        DB::transaction(function () use ($revoker, $subject, $role, $scope): void {
            DB::table('scope_role_user')
                ->where('user_id', $subject->getKey())
                ->where('role_id', $role->getKey())
                ->where('scope_id', $scope->getKey())
                ->delete();

            AuditLog::create([
                'user_id' => $revoker->getKey(),
                'action' => 'role.revoked',
                'auditable_type' => $subject->getMorphClass(),
                'auditable_id' => $subject->getKey(),
                'context' => ['role' => $role->name, 'scope_id' => $scope->getKey()],
            ]);
        });

        $this->scopes->forget($subject);
    }
}
