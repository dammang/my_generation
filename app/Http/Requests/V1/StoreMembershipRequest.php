<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'country.size' => 'Give the country as its two-letter code.',
        ];
    }

    public function rules(): array
    {
        // Required for a clan, optional for a tribe. A tribe is a wide door
        // and asking a stranger for their grandmother's name to walk through
        // it would turn people away for nothing; a clan is a family, and a
        // reviewer there is being asked whether this person is one of them.
        $joiningClan = $this->input('scope_type') === 'clan';
        $required = $joiningClan ? 'required' : 'sometimes';

        return [
            'scope_type' => ['required', Rule::in(['tribe', 'clan', 'family_branch'])],
            'scope_ulid' => ['required', 'string', 'size:26'],

            'applicant_name' => [$required, 'string', 'max:191'],
            'father_name' => [$required, 'string', 'max:191'],
            'mother_name' => [$required, 'string', 'max:191'],

            // Often genuinely unknown, which is the ordinary state of an oral
            // archive rather than an evasion.
            'grandfather_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'grandmother_name' => ['sometimes', 'nullable', 'string', 'max:191'],

            // Country only, and never anything narrower.
            'country' => [$required, 'string', 'size:2'],
            'contact' => [$required, 'string', 'max:191'],

            // Optional: an elder filling this in on somebody's borrowed phone
            // should not be stopped by a camera.
            'photo' => ['sometimes', 'nullable', 'image', 'max:8192'],
        ];
    }
}
