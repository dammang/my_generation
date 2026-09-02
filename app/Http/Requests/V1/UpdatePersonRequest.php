<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\Gender;
use App\Enums\PrivacyLevel;
use App\Enums\VerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        // `update`, not `updateDirectly`: a contributor who may edit but not
        // verify still gets to submit a change request, so authorization here
        // is about standing, not about which path the write takes.
        return $this->user()?->can('update', $this->route('person')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'gender' => ['sometimes', Rule::enum(Gender::class)],
            'birth' => ['sometimes', 'nullable', 'string', 'max:120'],
            'death' => ['sometimes', 'nullable', 'string', 'max:120'],
            'birth_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'death_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'biography' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'tribe_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('tribes', 'ulid')],
            'clan_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('clans', 'ulid')],
            'family_branch_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('family_branches', 'ulid')],
            'privacy_level' => ['sometimes', Rule::enum(PrivacyLevel::class)],
            'verification_status' => ['sometimes', Rule::enum(VerificationStatus::class)],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
