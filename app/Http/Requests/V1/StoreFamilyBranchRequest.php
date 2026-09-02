<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\FamilyBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreFamilyBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FamilyBranch::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tribe_ulid' => ['required', 'string', Rule::exists('tribes', 'ulid')->whereNull('deleted_at')],
            'clan_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('clans', 'ulid')->whereNull('deleted_at')],
            'ancestor_person_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            'origin_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:160', 'alpha_dash'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'current_region' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }
}
