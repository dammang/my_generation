<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Models\Union;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Union::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'partner_1_ulid' => ['required', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            // Nullable: single-parent families are real and common in
            // historical records, and refusing them loses the child's line.
            'partner_2_ulid' => [
                'sometimes', 'nullable', 'string', 'different:partner_1_ulid',
                Rule::exists('people', 'ulid')->whereNull('deleted_at'),
            ],
            'union_type' => ['sometimes', Rule::enum(UnionType::class)],
            'status' => ['sometimes', Rule::enum(UnionStatus::class)],
            'marriage' => ['sometimes', 'nullable', 'string', 'max:120'],
            'marriage_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'separation_date' => ['sometimes', 'nullable', 'date'],
            'divorce_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['partner_2_ulid.different' => 'A person cannot be in a union with themselves.'];
    }
}
