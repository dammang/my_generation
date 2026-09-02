<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\Certainty;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Models\Relationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Relationship::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // person is the parent/guardian, related_person the child/ward.
            'person_ulid' => ['required', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            'related_person_ulid' => [
                'required', 'string', 'different:person_ulid',
                Rule::exists('people', 'ulid')->whereNull('deleted_at'),
            ],
            'relationship_type' => ['sometimes', Rule::enum(RelationshipType::class)],
            'relationship_subtype' => ['sometimes', Rule::enum(RelationshipSubtype::class)],
            'certainty' => ['sometimes', Rule::enum(Certainty::class)],
            'custom_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'union_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('unions', 'ulid')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'related_person_ulid.different' => 'A person cannot be related to themselves.',
        ];
    }
}
