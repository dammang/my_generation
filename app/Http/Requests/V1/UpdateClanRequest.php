<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\Clan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('clan')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $clan = $this->route('clan');

        return [
            'parent_clan_ulid' => [
                'sometimes', 'nullable', 'string',
                Rule::exists('clans', 'ulid')->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => [
                'sometimes', 'string', 'max:160', 'alpha_dash',
                Rule::unique('clans', 'slug')
                    ->where('tribe_id', $clan->tribe_id)
                    ->where('deleted_token', 0)
                    ->ignore($clan->getKey()),
            ],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'history' => ['sometimes', 'nullable', 'string'],
            'level_label' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->has('parent_clan_ulid')) {
                return;
            }

            $clan = $this->route('clan');
            $parentUlid = $this->input('parent_clan_ulid');

            if ($parentUlid === null) {
                return;
            }

            $parent = Clan::where('ulid', $parentUlid)->first();

            if ($parent === null) {
                return;
            }

            if ($parent->tribe_id !== $clan->tribe_id) {
                $validator->errors()->add('parent_clan_ulid', 'The parent clan belongs to a different tribe.');

                return;
            }

            // A clan cannot be moved beneath itself or its own descendant. The
            // materialised path makes this one comparison rather than a walk,
            // and without it the hierarchy would detach from the tribe entirely.
            if ($parent->getKey() === $clan->getKey() || str_starts_with($parent->path, $clan->path)) {
                $validator->errors()->add(
                    'parent_clan_ulid',
                    'A clan cannot be placed beneath itself or one of its own sub-clans.'
                );
            }
        });
    }
}
