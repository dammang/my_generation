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

    /**
     * Masks against [$scope] rather than whoever the request is.
     *
     * The ambient viewer is right almost everywhere, and wrong in exactly one
     * place: the sign-in response, where the request has no authenticated user
     * yet because the credentials are still being checked. Serialising an
     * account's own claimed record there masked it to "Private" — the app told
     * somebody they were recorded in their own family archive as a person they
     * were not allowed to see.
     */
    public static function maskedFor(mixed $person, ViewerScope $scope): self
    {
        $resource = new self($person);
        $resource->scope = $scope;

        return $resource;
    }

    /** Overrides the ambient viewer for this instance only. */
    private ?ViewerScope $scope = null;

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
            // Whether the death is recorded as a bare fact rather than a date.
            'deceased_declared' => $this->deceased_declared,
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
            // Derived from the computed distance to the branch's founder, not
            // from the generation_id column.
            //
            // That column is assigned by hand: it was seeded wrong for the
            // fourth generation — grandchildren carried their parents' label —
            // it is never set for anybody added through the app, and nothing
            // maintains it when parentage changes. lineage_depths is computed
            // from the graph and was right the whole time; it simply was not
            // what the label read.
            'generation_label' => $this->generationLabel(),

            // Both reckonings, because families use both. A branch counts from
            // its own founder while the clan counts from the ancestor it
            // descends from, and "11th generation from Pu Zo, 1st generation of
            // Jasuan" is one person described twice, not a contradiction.
            'generation' => $this->generationDetail(),

            'merged_into' => $this->when(
                $this->merged_into_person_id !== null,
                fn () => $this->mergedInto?->ulid,
            ),
        ];
    }

    /**
     * "4th Generation", counted from the branch's apical ancestor.
     *
     * Falls back to the assigned generation where depths have not been
     * computed — a tribe whose branch names no founder has nothing to count
     * from, and saying nothing is better than saying something wrong.
     */
    private function generationLabel(): ?string
    {
        // Recorded by hand, and therefore deliberate. It wins over anything
        // derived: a tribe that does not count women's generations the way it
        // counts men's needs to be able to say so, and no amount of walking
        // the graph will work that out.
        if ($this->resource->relationLoaded('generation') && $this->generation !== null) {
            return $this->generation->generation_name
                ?? self::ordinal((int) $this->generation->generation_number).' Generation';
        }

        if (! $this->resource->relationLoaded('lineageDepths')) {
            return null;
        }

        $inner = $this->depthFrom($this->originId());

        if ($inner !== null) {
            return self::ordinal($inner + 1).' Generation';
        }

        $before = $this->generationsBeforeOrigin();

        if ($before !== null) {
            // Above the founder the branch counts from. They are not the
            // minus-first generation of anything — they are the people the
            // counting starts after, and a family says so in those words.
            return 'Pre-generation '.$before;
        }

        $outer = $this->depthFrom($this->clanAncestorId());

        if ($outer !== null) {
            return self::ordinal($outer + 1).' Generation';
        }

        // Married in. They have no descent from the founder, so they have no
        // depth of their own — they stand where their husband or wife stands,
        // which is what a family tree on paper has always shown.
        return $this->partnerGeneration($this->originId());
    }

    /**
     * The same person on both scales, for a summary that shows them together.
     *
     * Null when nothing is known, so a client renders nothing rather than a
     * row of empty fields.
     *
     * @return array<string, mixed>|null
     */
    private function generationDetail(): ?array
    {
        if (! $this->resource->relationLoaded('lineageDepths')) {
            return null;
        }

        $offset = $this->originOffset();

        $innerDepth = $this->depthFrom($this->originId());
        $inner = $innerDepth === null ? null : $innerDepth + 1;

        $outerDepth = $this->depthFrom($this->clanAncestorId());
        $outer = match (true) {
            $inner !== null && $offset !== null => $offset + $inner - 1,
            $outerDepth !== null => $outerDepth + 1,
            default => null,
        };

        $detail = array_filter([
            'number' => $inner,
            'origin' => $this->originName($this->origin()),
            'outer_number' => $outer,
            'outer_origin' => $this->originName($this->clanAncestor()),
            'before_origin' => $this->generationsBeforeOrigin(),
        ], fn ($value) => $value !== null);

        return $detail === [] ? null : $detail;
    }

    /**
     * How many generations above the founder this person stands.
     *
     * Only answerable when the clan names an older ancestor and the branch
     * knows where its own founder sits on that scale — otherwise "above" has
     * no distance attached to it.
     */
    private function generationsBeforeOrigin(): ?int
    {
        $offset = $this->originOffset();

        if ($offset === null || $this->depthFrom($this->originId()) !== null) {
            return null;
        }

        $outerDepth = $this->depthFrom($this->clanAncestorId());

        if ($outerDepth === null) {
            return null;
        }

        $before = $offset - ($outerDepth + 1);

        return $before > 0 ? $before : null;
    }

    private function depthFrom(?int $rootId): ?int
    {
        if ($rootId === null) {
            return null;
        }

        $row = $this->lineageDepths->firstWhere('root_person_id', $rootId);

        return $row === null ? null : (int) $row->depth;
    }

    /**
     * Whoever this person's generation is counted from.
     *
     * The clan's own origin first: it is the one the family says out loud, it
     * applies to everybody in the clan, and — unlike a branch founder — it
     * labels the people *above* it too, who belong to no branch at all and
     * were otherwise left with no generation whatsoever.
     */
    private function originId(): ?int
    {
        $fromClan = $this->resource->relationLoaded('clan')
            ? $this->clan?->counting_origin_person_id
            : null;

        if ($fromClan !== null) {
            return $fromClan;
        }

        return $this->resource->relationLoaded('familyBranch')
            ? $this->familyBranch?->ancestor_person_id
            : null;
    }

    /** The origin's own number on the older scale, from whichever set it. */
    private function originOffset(): ?int
    {
        $clan = $this->resource->relationLoaded('clan') ? $this->clan : null;

        if ($clan?->counting_origin_person_id !== null) {
            return $clan->generation_offset;
        }

        return $this->resource->relationLoaded('familyBranch')
            ? $this->familyBranch?->generation_offset
            : null;
    }

    /** The person the scale is named after, for "1st generation of Jasuan". */
    private function origin(): ?Person
    {
        $clan = $this->resource->relationLoaded('clan') ? $this->clan : null;

        if ($clan?->counting_origin_person_id !== null) {
            return $clan->relationLoaded('countingOrigin') ? $clan->countingOrigin : null;
        }

        $branch = $this->resource->relationLoaded('familyBranch') ? $this->familyBranch : null;

        return $branch !== null && $branch->relationLoaded('ancestor') ? $branch->ancestor : null;
    }

    private function clanAncestorId(): ?int
    {
        return $this->resource->relationLoaded('clan')
            ? $this->clan?->ancestor_person_id
            : null;
    }

    private function clanAncestor(): ?Person
    {
        $clan = $this->resource->relationLoaded('clan') ? $this->clan : null;

        return $clan !== null && $clan->relationLoaded('ancestor') ? $clan->ancestor : null;
    }

    /**
     * A founder's name is the scale's name — "of Jasuan" means nothing without
     * it — and a founder is, by definition, somebody the family already names
     * publicly as where it begins.
     */
    private function originName(?Person $ancestor): ?string
    {
        return $ancestor?->display_name;
    }

    /** The generation of whoever this person is partnered with, if any. */
    private function partnerGeneration(mixed $root): ?string
    {
        if ($root === null) {
            return null;
        }

        foreach (['unionsAsPartner1' => 'partner2', 'unionsAsPartner2' => 'partner1'] as $side => $other) {
            if (! $this->resource->relationLoaded($side)) {
                continue;
            }

            foreach ($this->resource->{$side} as $union) {
                $partner = $union->{$other};

                if ($partner === null || ! $partner->relationLoaded('lineageDepths')) {
                    continue;
                }

                $row = $partner->lineageDepths->firstWhere('root_person_id', $root);

                if ($row !== null) {
                    return self::ordinal($row->depth + 1).' Generation';
                }
            }
        }

        return null;
    }

    /** 1st, 2nd, 3rd, 4th … 11th, 12th, 13th. */
    private static function ordinal(int $n): string
    {
        $suffix = match (true) {
            in_array($n % 100, [11, 12, 13], true) => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
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

        return self::$resolver->mask($this->scope ?? self::$viewer, $this->resource);
    }
}
