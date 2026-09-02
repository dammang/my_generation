<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Enums\Certainty;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Union;
use App\Services\Integrity\GenealogyWarning;
use App\Services\Integrity\GenealogyWarnings;

/**
 * Writes one directed edge, in canonical direction.
 *
 * For parent_child, person_id is ALWAYS the parent. For symmetric assertions
 * (sibling_asserted) the pair is normalised by id so the unique key actually
 * prevents the same claim being entered from either side.
 */
class CreateRelationship
{
    public function __construct(
        private readonly AssertNoCycle $assertNoCycle,
        private readonly GenealogyWarnings $warnings,
    ) {}

    /**
     * @return array{0: Relationship, 1: array<int, GenealogyWarning>}
     */
    public function handle(
        Person $from,
        Person $to,
        RelationshipType $type = RelationshipType::ParentChild,
        RelationshipSubtype $subtype = RelationshipSubtype::Biological,
        ?Union $union = null,
        Certainty $certainty = Certainty::Probable,
        ?string $customLabel = null,
    ): array {
        [$from, $to] = $this->canonicalise($from, $to, $type);

        if ($type === RelationshipType::ParentChild) {
            // Hard error, and the only one here: a cycle makes every traversal
            // below it wrong rather than merely doubtful.
            $this->assertNoCycle->handle($from->getKey(), $to->getKey());
        }

        $relationship = Relationship::firstOrNew([
            'person_id' => $from->getKey(),
            'related_person_id' => $to->getKey(),
            'relationship_type' => $type,
            'relationship_subtype' => $subtype,
        ]);

        $relationship->fill([
            'is_biological' => match ($subtype) {
                RelationshipSubtype::Biological => true,
                RelationshipSubtype::Adoptive, RelationshipSubtype::Step, RelationshipSubtype::Foster => false,
                default => null,
            },
            'union_id' => $union?->getKey(),
            'certainty' => $certainty,
            'custom_label' => $customLabel,
        ]);

        $relationship->save();

        $warnings = $type === RelationshipType::ParentChild
            ? $this->warnings->forParentChild($from, $to)
            : [];

        return [$relationship, $warnings];
    }

    /**
     * @return array{0: Person, 1: Person}
     */
    private function canonicalise(Person $from, Person $to, RelationshipType $type): array
    {
        // Sibling assertions are symmetric, so the pair is ordered by id.
        // Everything else has an inherent direction that must be preserved.
        if ($type === RelationshipType::SiblingAsserted && $from->getKey() > $to->getKey()) {
            return [$to, $from];
        }

        return [$from, $to];
    }
}
