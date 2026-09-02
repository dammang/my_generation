<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

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
            'role' => [
                'required', 'string',
                // super-admin is deliberately absent: it is a global bypass and
                // must never be grantable from a scoped endpoint.
                Rule::in(['tribe-admin', 'clan-admin', 'family-admin', 'historian', 'contributor', 'member', 'viewer']),
            ],
        ];
    }
}
