<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\PrivacyLevel;
use App\Models\PersonEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PersonEvent::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'person_ulid' => ['required', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            'event_type' => ['required', 'string', Rule::exists('event_types', 'slug')],
            'title' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // Free text, like every other date in this system.
            'date' => ['sometimes', 'nullable', 'string', 'max:120'],
            'place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'from_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'to_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'privacy_level' => ['sometimes', 'nullable', Rule::enum(PrivacyLevel::class)],
        ];
    }
}
