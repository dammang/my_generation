<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Access\AssignScopedRole;
use App\Actions\Access\RevokeScopedRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AssignScopeRoleRequest;
use App\Models\Scope;
use App\Models\User;
use App\Services\Permissions\ScopeLocator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Scoped role assignment: "you are an admin of the Guite clan."
 *
 * The escalation guard lives in the action, not here, so it applies equally to
 * Filament and to any future caller.
 */
class ScopeRoleController extends Controller
{
    public function __construct(private readonly ScopeLocator $scopes) {}

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
