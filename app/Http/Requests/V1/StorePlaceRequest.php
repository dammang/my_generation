<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Places are shared infrastructure: anyone who can contribute people may add
 * the village they came from, because requiring an admin here would stall data
 * entry for no safety gain.
 */
class StorePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Place::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            // A string, not an enum: jurisdictions differ and a township in one
            // country is a district in another.
            'type' => ['required', 'string', 'max:40'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('country_code')) {
            $this->merge(['country_code' => $this->string('country_code')->upper()->toString()]);
        }
    }
}
