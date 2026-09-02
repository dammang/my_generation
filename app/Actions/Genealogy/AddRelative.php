<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Enums\ChildRelationshipType;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Enums\UnionStatus;
use App\Enums\UnionType;
use App\Exceptions\AmbiguousUnionException;
use App\Models\Person;
use App\Models\Union;
use App\Models\UnionChild;
use App\Models\User;
use App\Services\Integrity\GenealogyWarnings;
use App\Support\WriteOutcome;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Add Son" → a person, a union, two parent edges and a birth-order row.
 *
 * The contributor picks a relationship label. They never learn that a union row
 * exists, and they should not have to: the whole point of modelling this as a
 * graph is that the data entry stays as simple as the family it describes.
 *
 * Everything here runs in one transaction. A half-written family — a person with
 * no edges, or a union_children row whose parent edges never landed — is worse
 * than a failed request, because nothing in the UI would reveal it.
 */
class AddRelative
{
    /** Relationship labels the API accepts. */
    public const RELATIONS = [
        'father', 'mother', 'parent',
        'spouse',
        'son', 'daughter', 'child',
        'brother', 'sister', 'sibling',
        'guardian', 'other',
    ];

    public function __construct(
        private readonly CreatePerson $createPerson,
        private readonly CreateRelationship $createRelationship,
        private readonly AddChildToUnion $addChildToUnion,
        private readonly GenealogyWarnings $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  the new person's attributes
     */
    public function handle(
        User $author,
        Person $anchor,
        string $relation,
        array $attributes,
        ?Union $union = null,
        RelationshipSubtype $subtype = RelationshipSubtype::Biological,
        ?string $customLabel = null,
    ): WriteOutcome {
        return DB::transaction(function () use (
            $author, $anchor, $relation, $attributes, $union, $subtype, $customLabel
        ): WriteOutcome {
            // The new person inherits the anchor's placement unless told
            // otherwise — a relative of somebody in the Guite clan is almost
            // always in the Guite clan, and making the contributor restate it
            // every time is how placement data goes missing.
            $person = $this->createPerson->handle($author, [
                'tribe_id' => $anchor->tribe_id,
                'clan_id' => $anchor->clan_id,
                'family_branch_id' => $anchor->family_branch_id,
                'privacy_level' => $anchor->privacy_level,
                ...$attributes,
            ]);

            $warnings = $this->warnings->forPerson($person);
            $created = ['people' => 1, 'relationships' => 0, 'unions' => 0, 'union_children' => 0];

            $result = match ($relation) {
                'father', 'mother', 'parent' => $this->addParent($anchor, $person, $subtype),
                'spouse' => $this->addSpouse($anchor, $person),
                'son', 'daughter', 'child' => $this->addChild($anchor, $person, $union, $subtype),
                'brother', 'sister', 'sibling' => $this->addSibling($anchor, $person, $subtype),
                'guardian' => $this->addGuardian($anchor, $person),
                default => $this->addOther($anchor, $person, $customLabel),
            };

            return new WriteOutcome(
                record: $person->refresh(),
                warnings: [...$warnings, ...$result['warnings']],
                created: [...$created, ...$result['created']],
            );
        });
    }

    /**
     * A new parent joins the child's existing union where there is a free
     * slot, so adding a father after a mother produces one couple rather than
     * two single-parent families.
     *
     * @return array{warnings: array<int, mixed>, created: array<string, int>}
     */
    private function addParent(Person $child, Person $parent, RelationshipSubtype $subtype): array
    {
        $existing = UnionChild::where('person_id', $child->getKey())->first();
        $union = $existing?->union_id === null ? null : Union::find($existing->union_id);
        $created = ['relationships' => 1, 'unions' => 0, 'union_children' => 0];

        if ($union !== null && $union->partner_2_id === null && $union->partner_1_id !== $parent->getKey()) {
            $union->partner_2_id = $parent->getKey();
            $union->save();   // UnionObserver normalises the pair order
        } elseif ($union === null) {
            $union = Union::create([
                'partner_1_id' => $parent->getKey(),
                'union_type' => UnionType::Unknown,
                'status' => UnionStatus::Unknown,
            ]);

            UnionChild::create([
                'union_id' => $union->getKey(),
                'person_id' => $child->getKey(),
                'relationship_type' => $this->childKindFor($subtype),
            ]);

            $created['unions'] = 1;
            $created['union_children'] = 1;
        }

        [, $warnings] = $this->createRelationship->handle(
            from: $parent,
            to: $child,
            type: RelationshipType::ParentChild,
            subtype: $subtype,
            union: $union,
        );

        return ['warnings' => $warnings, 'created' => $created];
    }

    /** @return array{warnings: array<int, mixed>, created: array<string, int>} */
    private function addSpouse(Person $anchor, Person $spouse): array
    {
        $union = Union::create([
            'partner_1_id' => $anchor->getKey(),
            'partner_2_id' => $spouse->getKey(),
            'union_type' => UnionType::Marriage,
        ]);

        return [
            'warnings' => $this->warnings->forUnion($union->refresh(), $anchor, $spouse),
            'created' => ['unions' => 1, 'relationships' => 0, 'union_children' => 0],
        ];
    }

    /**
     * Which union the child belongs to matters: guessing would attach them to
     * the wrong marriage, and that error is invisible until somebody notices
     * the chart is wrong a generation later.
     *
     * @return array{warnings: array<int, mixed>, created: array<string, int>}
     */
    private function addChild(Person $anchor, Person $child, ?Union $union, RelationshipSubtype $subtype): array
    {
        $unions = $anchor->allUnions();
        $created = ['unions' => 0, 'union_children' => 1, 'relationships' => 0];

        if ($union === null) {
            $union = match ($unions->count()) {
                0 => null,
                1 => $unions->first(),
                default => throw new AmbiguousUnionException($this->describeUnions($anchor, $unions)),
            };
        }

        if ($union === null) {
            // A single-parent family is real and common in historical records.
            $union = Union::create([
                'partner_1_id' => $anchor->getKey(),
                'union_type' => UnionType::Unknown,
                'status' => UnionStatus::Unknown,
            ]);
            $created['unions'] = 1;
        }

        $warnings = $this->addChildToUnion->handle(
            union: $union,
            child: $child,
            kind: $this->childKindFor($subtype),
        );

        $created['relationships'] = count(array_filter([$union->partner_1_id, $union->partner_2_id]));

        return ['warnings' => $warnings, 'created' => $created];
    }

    /**
     * A sibling is created by attaching the new person to the same parents,
     * never by writing a sibling row — siblings are derived. The asserted form
     * exists only when the parents genuinely are not known.
     *
     * @return array{warnings: array<int, mixed>, created: array<string, int>}
     */
    private function addSibling(Person $anchor, Person $sibling, RelationshipSubtype $subtype): array
    {
        $link = UnionChild::where('person_id', $anchor->getKey())->first();

        if ($link !== null) {
            $union = Union::findOrFail($link->union_id);

            $warnings = $this->addChildToUnion->handle(
                union: $union,
                child: $sibling,
                kind: $this->childKindFor($subtype),
            );

            return [
                'warnings' => $warnings,
                'created' => [
                    'unions' => 0,
                    'union_children' => 1,
                    'relationships' => count(array_filter([$union->partner_1_id, $union->partner_2_id])),
                ],
            ];
        }

        $parents = $anchor->parentEdges()->pluck('person_id');

        if ($parents->isNotEmpty()) {
            $warnings = [];

            foreach ($parents as $parentId) {
                [, $edgeWarnings] = $this->createRelationship->handle(
                    from: Person::findOrFail($parentId),
                    to: $sibling,
                    type: RelationshipType::ParentChild,
                    subtype: $subtype,
                );
                $warnings = [...$warnings, ...$edgeWarnings];
            }

            return [
                'warnings' => $warnings,
                'created' => ['relationships' => $parents->count(), 'unions' => 0, 'union_children' => 0],
            ];
        }

        // "These two are brothers, we do not know their parents" — a real and
        // common situation in oral genealogy, and the only case that justifies
        // storing a sibling row at all.
        [, $warnings] = $this->createRelationship->handle(
            from: $anchor,
            to: $sibling,
            type: RelationshipType::SiblingAsserted,
            subtype: RelationshipSubtype::Unknown,
        );

        return [
            'warnings' => $warnings,
            'created' => ['relationships' => 1, 'unions' => 0, 'union_children' => 0],
        ];
    }

    /** @return array{warnings: array<int, mixed>, created: array<string, int>} */
    private function addGuardian(Person $ward, Person $guardian): array
    {
        [, $warnings] = $this->createRelationship->handle(
            from: $guardian,
            to: $ward,
            type: RelationshipType::Guardian,
            subtype: RelationshipSubtype::Unknown,
        );

        return ['warnings' => $warnings, 'created' => ['relationships' => 1, 'unions' => 0, 'union_children' => 0]];
    }

    /** @return array{warnings: array<int, mixed>, created: array<string, int>} */
    private function addOther(Person $anchor, Person $other, ?string $customLabel): array
    {
        [, $warnings] = $this->createRelationship->handle(
            from: $anchor,
            to: $other,
            type: RelationshipType::Other,
            subtype: RelationshipSubtype::Custom,
            customLabel: $customLabel,
        );

        return ['warnings' => $warnings, 'created' => ['relationships' => 1, 'unions' => 0, 'union_children' => 0]];
    }

    private function childKindFor(RelationshipSubtype $subtype): ChildRelationshipType
    {
        return match ($subtype) {
            RelationshipSubtype::Adoptive => ChildRelationshipType::Adoptive,
            RelationshipSubtype::Step => ChildRelationshipType::Step,
            RelationshipSubtype::Foster => ChildRelationshipType::Foster,
            default => ChildRelationshipType::Biological,
        };
    }

    /**
     * @param  Collection<int, Union>  $unions
     * @return array<int, array{ulid: string, label: string}>
     */
    private function describeUnions(Person $anchor, $unions): array
    {
        return $unions->map(function (Union $union) use ($anchor): array {
            $partnerId = $union->partnerOf($anchor->getKey());
            $partner = $partnerId === null ? null : Person::find($partnerId);

            return [
                'ulid' => $union->ulid,
                'label' => trim(sprintf(
                    '%s%s',
                    $partner?->display_name ?? 'Unknown partner',
                    $union->marriage_year === null ? '' : ", married {$union->marriage_year}",
                )),
            ];
        })->values()->all();
    }
}
