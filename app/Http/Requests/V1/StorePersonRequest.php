<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\Gender;
use App\Enums\PrivacyLevel;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Note what is NOT required: no name part, no date, no place.
 *
 * Oral genealogy routinely records a person by a single name and a relationship
 * and nothing else. Requiring more would make the platform unable to hold the
 * very records it exists to preserve.
 */
class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Person::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Client-minted, so a person created offline can be referred to
            // before the server has ever seen them.
            'ulid' => [
                'sometimes',
                'string',
                'size:26',
                'regex:/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/',
                Rule::unique('people', 'ulid'),
            ],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', Rule::enum(Gender::class)],

            // Free text, deliberately: "abt. 1902", "1920s", "before the war".
            // The parser keeps the wording and derives what it can.
            'birth' => ['sometimes', 'nullable', 'string', 'max:120'],
            'death' => ['sometimes', 'nullable', 'string', 'max:120'],

            'birth_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'death_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'biography' => ['sometimes', 'nullable', 'string', 'max:20000'],

            'tribe_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('tribes', 'ulid')],
            'clan_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('clans', 'ulid')],
            'family_branch_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('family_branches', 'ulid')],
            'privacy_level' => ['sometimes', Rule::enum(PrivacyLevel::class)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $named = collect(['first_name', 'last_name', 'native_name', 'nickname', 'display_name'])
                ->contains(fn (string $field) => filled($this->input($field)));

            if (! $named) {
                $validator->errors()->add('display_name', 'Give the person at least one name.');
            }
        });
    }
}
