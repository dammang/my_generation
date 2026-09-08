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

            // The name as the family writes it. Names here do not split into a
            // first and a last — "PAU KHUA NEM (KHUPMU)" is one name — so the
            // whole thing has to be correctable, and it was not accepted at
            // all: every screen reads this column and nothing could change it.
            'display_name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', Rule::enum(Gender::class)],
            'birth' => ['sometimes', 'nullable', 'string', 'max:120'],
            'death' => ['sometimes', 'nullable', 'string', 'max:120'],
            'birth_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'death_place_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('places', 'ulid')],
            'biography' => ['sometimes', 'nullable', 'string', 'max:20000'],

            // "They have died, nobody knows when." Kept apart from the date
            // fields because it is a different claim, and because a family
            // that cannot give a year must still be able to say this much.
            'deceased_declared' => ['sometimes', 'boolean'],
            'tribe_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('tribes', 'ulid')],
            'clan_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('clans', 'ulid')],
            'family_branch_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('family_branches', 'ulid')],
            // Assigned by hand, and deliberately allowed to disagree with the
            // computed depth: somebody who married in is counted at their
            // partner's generation, not at their own distance from a founder
            // this archive may not even hold.
            'generation_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('generations', 'ulid')],
            'privacy_level' => ['sometimes', Rule::enum(PrivacyLevel::class)],
            'verification_status' => ['sometimes', Rule::enum(VerificationStatus::class)],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
