<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\PrivacyLevel;
use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tribe')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => [
                'sometimes', 'string', 'max:160', 'alpha_dash',
                Rule::unique('tribes', 'slug')
                    ->where('deleted_token', 0)
                    ->ignore($this->route('tribe')->getKey()),
            ],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'history' => ['sometimes', 'nullable', 'string'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'region' => ['sometimes', 'nullable', 'string', 'max:150'],
            'default_privacy_level' => ['sometimes', Rule::enum(PrivacyLevel::class)],
            'status' => ['sometimes', Rule::enum(RecordStatus::class)],
        ];
    }
}
