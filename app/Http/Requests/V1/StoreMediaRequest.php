<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\MediaCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Typed by content, not by extension: a .jpg is whatever its
            // bytes say it is, and the extension is the attacker's to choose.
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic', 'max:20480'],
            'person_ulid' => ['required', 'string', 'size:26'],
            'collection' => ['sometimes', Rule::enum(MediaCollection::class)],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Private by default, and only ever public by an explicit choice.
            'is_private' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'That file is not an image we can store.',
            'file.max' => 'Photographs must be under 20 MB.',
        ];
    }
}
