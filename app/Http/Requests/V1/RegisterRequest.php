<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:191',
                // deleted_token keeps the unique index honest across soft
                // deletes; the rule has to agree with it.
                Rule::unique('users', 'email')->where('deleted_token', 0),
            ],
            'password' => ['required', 'confirmed', Password::defaults()->min(8)->letters()->numbers()],
            'locale' => ['sometimes', 'string', 'max:10'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'An account already exists for this email address.',
        ];
    }
}
