<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\DatePrecision;
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
    private static ?PersonVisibilityResolver $resolver = null;

    private static ?ViewerScope $viewer = null;

    /** Cleared between requests by FlushRequestScopedState. */
    public static function forgetRequestState(): void
    {
        self::$resolver = null;
        self::$viewer = null;
    }

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

    /**
     * Built from the raw columns rather than the UncertainDate value object.
     *
     * A tree can carry several hundred people, and the model casts date columns
     * to Carbon on access: constructing two Carbon instances per person purely
     * to render a year dominated the response time on large trees. The value
     * object is still the right abstraction everywhere a single record is
     * examined; this is the one path where the volume justifies reading the
     * columns directly.
     *
     * @return array<string, mixed>|null
     */
    private function dateFacts(string $prefix, FieldMask $mask): ?array
    {
        if (! $mask->years) {
            return null;
        }

        $year = $this->getAttribute("{$prefix}_year");

        if ($year === null) {
            return null;
        }

        $precision = $this->getAttribute("{$prefix}_date_precision");
        $precision = $precision instanceof DatePrecision
            ? $precision
            : DatePrecision::tryFrom((string) $precision) ?? DatePrecision::Unknown;

        // Year-only when the viewer is not close enough for the exact date.
        if (! $mask->exactDates) {
            return [
                'year' => (int) $year,
                'display' => (string) $year,
                'precision' => DatePrecision::Year->value,
                'date' => null,
            ];
        }

        $raw = $this->getRawOriginal("{$prefix}_date");
        $date = $raw === null ? null : substr((string) $raw, 0, 10);

        return [
            'year' => (int) $year,
            // The source's own wording wins: it is the primary evidence.
            'display' => $this->getAttribute("{$prefix}_date_text")
                ?? $this->formatDate($precision, $date, (int) $year),
            'precision' => $precision->value,
            'date' => $date,
        ];
    }

    /** Cheap formatting that avoids constructing a date object per person. */
    private function formatDate(DatePrecision $precision, ?string $date, int $year): ?string
    {
        return match ($precision) {
            DatePrecision::Exact => $date,
            DatePrecision::Month => $date === null ? (string) $year : substr($date, 0, 7),
            DatePrecision::Decade => $year.'s',
            DatePrecision::About => 'abt. '.$year,
            DatePrecision::Before => 'before '.$year,
            DatePrecision::After => 'after '.$year,
            DatePrecision::Unknown => null,
            default => (string) $year,
        };
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
        // Resolved once per request: a large tree serialises hundreds of
        // people, and two container lookups each is a cost with no benefit.
        self::$resolver ??= app(PersonVisibilityResolver::class);
        self::$viewer ??= app(ViewerScope::class);

        return self::$resolver->mask(self::$viewer, $this->resource);
    }
}
