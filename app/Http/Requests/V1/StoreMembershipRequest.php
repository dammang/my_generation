<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scope_type' => ['required', Rule::in(['tribe', 'clan', 'family_branch'])],
            'scope_ulid' => ['required', 'string', 'size:26'],
        ];
    }
}
