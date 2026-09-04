<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\PrivacyLevel;
use App\Enums\StoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'person_ulid' => ['sometimes', 'nullable', 'string', 'size:26'],
            'story_type' => ['sometimes', Rule::enum(StoryType::class)],
            // Defaults to Family rather than Public: a story about living
            // relatives is the normal case, and the safe default for one is
            // not the one that publishes it.
            'visibility' => ['sometimes', Rule::enum(PrivacyLevel::class)],
            'era_start_year' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2200'],
            'era_end_year' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2200'],
        ];
    }
}
