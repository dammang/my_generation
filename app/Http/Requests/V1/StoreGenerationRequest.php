<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\Clan;
use App\Models\Tribe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Generations are labels. Nothing in the traversal engine depends on them, so
 * the rules here are about coherence, not correctness of the tree.
 */
class StoreGenerationRequest extends FormRequest
{
    /**
     * A generation belonging to one clan is that clan's to name.
     *
     * Authorised against the clan when one is given, and against the tribe
     * otherwise: a tribe-wide label affects every family in it, but "the
     * fourth generation of the Guite" is a statement about the Guite, and
     * requiring tribe authority for it would leave a clan unable to number
     * its own people.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->filled('clan_ulid')) {
            $clan = Clan::where('ulid', $this->string('clan_ulid'))->first();

            return $clan !== null && $user->can('update', $clan);
        }

        $tribe = Tribe::where('ulid', $this->string('tribe_ulid'))->first();

        return $tribe !== null && $user->can('update', $tribe);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tribe_ulid' => ['required', 'string', Rule::exists('tribes', 'ulid')->whereNull('deleted_at')],
            'clan_ulid' => ['sometimes', 'nullable', 'string', Rule::exists('clans', 'ulid')->whereNull('deleted_at')],
            'generation_number' => ['required', 'integer', 'between:-50,200'],
            'generation_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'local_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'estimated_start_year' => ['sometimes', 'nullable', 'integer', 'between:-3000,2200'],
            'estimated_end_year' => ['sometimes', 'nullable', 'integer', 'between:-3000,2200', 'gte:estimated_start_year'],
        ];
    }
}
