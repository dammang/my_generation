<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Actions\Access\AssignScopedRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignScopeRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_ulid' => ['required', 'string', Rule::exists('users', 'ulid')->where('deleted_token', 0)],
            'scope_type' => ['required', Rule::in(['tribe', 'clan', 'family_branch'])],
            'scope_ulid' => ['required', 'string', 'size:26'],
            // The list lives on the action, which also enforces it — one copy,
            // so a role added here can never be one the grant path refuses.
            'role' => ['required', 'string', Rule::in(AssignScopedRole::ASSIGNABLE)],
        ];
    }
}
