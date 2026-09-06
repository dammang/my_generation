<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A query string has no types, so "true" arrives as the four characters.
     *
     * Laravel's `boolean` rule accepts true, false, 1, 0, "1" and "0" — and
     * not "true", which is what every HTTP client sends for a boolean in a
     * URL. The app's own "find myself in the archive" search sends
     * `living=true` and was refused every single time, so claiming a profile
     * had never once worked for anybody.
     *
     * Normalised rather than validated more loosely, so the rule still refuses
     * something that is not a boolean at all instead of quietly reading it as
     * false.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('living')) {
            return;
        }

        $this->merge([
            'living' => filter_var($this->input('living'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:120'],
            'tribe' => ['sometimes', 'string', 'size:26'],
            'clan' => ['sometimes', 'string', 'size:26'],
            'branch' => ['sometimes', 'string', 'size:26'],
            'living' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
