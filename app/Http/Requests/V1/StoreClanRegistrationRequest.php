<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClanRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tribe_ulid' => ['required', 'string', Rule::exists('tribes', 'ulid')],
            'name' => ['required', 'string', 'max:150'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],

            // A clan inside a clan is how sub-clans are recorded.
            'parent_clan_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('clans', 'ulid')],

            // Optional: somebody may know the name of their clan long before
            // they can point at the person it descends from.
            'ancestor_person_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('people', 'ulid')],

            'statement' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
