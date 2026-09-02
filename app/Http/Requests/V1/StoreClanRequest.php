<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\Clan;
use App\Models\Tribe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreClanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Clan::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tribe_ulid' => ['required', 'string', Rule::exists('tribes', 'ulid')->whereNull('deleted_at')],
            'parent_clan_ulid' => [
                'sometimes', 'nullable', 'string',
                Rule::exists('clans', 'ulid')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:160', 'alpha_dash'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'history' => ['sometimes', 'nullable', 'string'],
            // The tribe's own word for this level of the hierarchy. Depth is
            // data, not schema: nothing assumes three levels, or any number.
            'level_label' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('parent_clan_ulid')) {
                return;
            }

            $parent = Clan::where('ulid', $this->string('parent_clan_ulid'))->first();
            $tribeId = Tribe::where('ulid', $this->string('tribe_ulid'))->value('id');

            if ($parent !== null && $parent->tribe_id !== $tribeId) {
                $validator->errors()->add(
                    'parent_clan_ulid',
                    'The parent clan belongs to a different tribe.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }
}
