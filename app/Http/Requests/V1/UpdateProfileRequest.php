<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => [
                'sometimes', 'string', 'email:rfc', 'max:191',
                Rule::unique('users', 'email')
                    ->where('deleted_token', 0)
                    ->ignore($this->user()->getKey()),
            ],
            'locale' => ['sometimes', 'string', 'max:10'],
        ];
    }
}
