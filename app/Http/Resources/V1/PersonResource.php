<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Person;
use App\Services\Privacy\FieldMask;
use App\Services\Privacy\PersonVisibilityResolver;
use App\Services\Privacy\ViewerScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin Person
 *
 * The single serialisation path for a person.
 *
 * Every field goes through the FieldMask — there is no branch here that reads
 * an attribute without asking first. The client is a renderer: it must never
 * receive something it is merely expected not to display.
 */
class PersonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mask = $this->mask();

        if (! $mask->visible) {
            return $this->placeholder();
        }

        return [
            'ulid' => $this->ulid,
            'display_name' => $mask->name ? $this->display_name : 'Private',
            'first_name' => $mask->name ? $this->first_name : null,
            'middle_name' => $mask->name ? $this->middle_name : null,
            'last_name' => $mask->name ? $this->last_name : null,
            'native_name' => $mask->nativeName ? $this->native_name : null,
            'nickname' => $mask->name ? $this->nickname : null,
            'gender' => $this->gender->value,

            'birth' => $this->dateFacts('birth', $mask),
            'death' => $this->dateFacts('death', $mask),

            'is_living' => $this->is_living,
            'verification_status' => $this->verification_status->value,
            'has_open_dispute' => (bool) $this->has_open_dispute,
            'privacy_level' => $this->privacy_level->value,
            'redacted' => $mask->redacted,

            'biography' => $mask->biography ? $this->biography : null,
            'photo_url' => $mask->media ? $this->photoUrl() : null,

            'birth_place' => $mask->places
                ? PlaceResource::make($this->whenLoaded('birthPlace'))
                : null,
            'death_place' => $mask->places
                ? PlaceResource::make($this->whenLoaded('deathPlace'))
                : null,

            'tribe' => $this->whenLoaded('tribe', fn () => [
                'ulid' => $this->tribe->ulid,
                'name' => $this->tribe->name,
            ]),
            'clan' => $this->whenLoaded('clan', fn () => [
                'ulid' => $this->clan->ulid,
                'name' => $this->clan->name,
            ]),
            'family_branch' => $this->whenLoaded('familyBranch', fn () => [
                'ulid' => $this->familyBranch->ulid,
                'name' => $this->familyBranch->name,
            ]),
            'generation_label' => $this->whenLoaded(
                'generation',
                fn () => $this->generation?->generation_name,
            ),

            'merged_into' => $this->when(
                $this->merged_into_person_id !== null,
                fn () => $this->mergedInto?->ulid,
            ),
        ];
    }

    /**
     * A person the viewer may not see still occupies a position in the graph.
     * Withholding the node entirely would misrepresent everyone else's lineage,
     * so the shape survives and the content does not.
     *
     * @return array<string, mixed>
     */
    private function placeholder(): array
    {
        return [
            'ulid' => $this->ulid,
            'display_name' => 'Private',
            'gender' => 'unknown',
            'is_living' => true,
            'redacted' => true,
            'placeholder' => true,
        ];
    }

    /** @return array<string, mixed>|null */
    private function dateFacts(string $prefix, FieldMask $mask): ?array
    {
        if (! $mask->years) {
            return null;
        }

        $fact = $this->dateFact($prefix);

        if (! $fact->isKnown()) {
            return null;
        }

        // Year-only when the viewer is not close enough for the exact date.
        if (! $mask->exactDates) {
            return [
                'year' => $fact->year,
                'display' => (string) $fact->year,
                'precision' => 'year',
                'date' => null,
            ];
        }

        return [
            'year' => $fact->year,
            'display' => $fact->display(),
            'precision' => $fact->precision->value,
            'date' => $fact->date?->toDateString(),
        ];
    }

    private function photoUrl(): ?string
    {
        $media = $this->whenLoaded('profileMedia');

        return $media instanceof MissingValue || $media === null
            ? null
            : ($media->conversions['thumb'] ?? $media->path);
    }

    private function mask(): FieldMask
    {
        return app(PersonVisibilityResolver::class)->mask(
            app(ViewerScope::class),
            $this->resource,
        );
    }
}
