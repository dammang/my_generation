<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('family_branch')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $branch = $this->route('family_branch');

        return [
            'clan_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('clans', 'ulid')->whereNull('deleted_at')],
            'ancestor_person_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            'origin_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')->whereNull('deleted_at')],
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => [
                'sometimes', 'string', 'max:160', 'alpha_dash',
                Rule::unique('family_branches', 'slug')
                    ->where('tribe_id', $branch->tribe_id)
                    ->where('deleted_token', 0)
                    ->ignore($branch->getKey()),
            ],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'current_region' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
