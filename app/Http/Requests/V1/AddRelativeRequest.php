<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Actions\Genealogy\AddRelative;
use App\Enums\Gender;
use App\Enums\RelationshipSubtype;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddRelativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Person::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'relation' => ['required', 'string', Rule::in(AddRelative::RELATIONS)],
            'person' => ['required', 'array'],
            'person.first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'person.middle_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'person.last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'person.native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'person.nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'person.display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'person.gender' => ['sometimes', Rule::enum(Gender::class)],
            'person.birth' => ['sometimes', 'nullable', 'string', 'max:120'],
            'person.death' => ['sometimes', 'nullable', 'string', 'max:120'],
            'person.birth_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],

            // Required only when the anchor has more than one union; the action
            // raises UNION_AMBIGUOUS with the choices rather than guessing.
            'union_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('unions', 'ulid')->whereNull('deleted_at')],
            'relationship_subtype' => ['sometimes', Rule::enum(RelationshipSubtype::class)],
            'custom_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'client_operation_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $person = (array) $this->input('person', []);

            $named = collect(['first_name', 'last_name', 'native_name', 'nickname', 'display_name'])
                ->contains(fn (string $field) => filled($person[$field] ?? null));

            if (! $named) {
                $validator->errors()->add('person.display_name', 'Give the relative at least one name.');
            }
        });
    }
}
