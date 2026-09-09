<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\PrivacyLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One field, because that is the point: changing who may see you must not
 * be able to change anything else about the record on the way through.
 */
class UpdateVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setVisibility', $this->route('person')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'privacy_level' => ['required', Rule::enum(PrivacyLevel::class)],
        ];
    }
}
