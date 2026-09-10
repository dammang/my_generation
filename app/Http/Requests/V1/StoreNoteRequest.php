<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\NoteAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::enum(NoteAudience::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Somebody with no record of their own has no line, so a note
                // for their descendants would be a note nobody could ever
                // read. Said plainly rather than written and left silent.
                $wantsLine = $this->input('audience') === NoteAudience::Descendants->value;

                if ($wantsLine && $this->user()?->person_id === null) {
                    $validator->errors()->add(
                        'audience',
                        'Find yourself in the archive first, so the archive knows who your descendants are.',
                    );
                }
            },
        ];
    }
}
