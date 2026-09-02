<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Enums\ChildRelationshipType;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Exceptions\GenealogyRuleException;
use App\Models\Person;
use App\Models\Union;
use App\Models\UnionChild;
use App\Services\Integrity\GenealogyWarning;
use Illuminate\Support\Facades\DB;

/**
 * Places a child under a couple.
 *
 * Writes the grouping row AND a parent edge for each partner, in one
 * transaction, because the two are only meaningful together: union_children
 * says where the child sits on the chart, while the relationships rows are what
 * actually assert parentage. A union_children row without its edges would draw
 * a family that the graph does not contain.
 */
class AddChildToUnion
{
    public function __construct(private readonly CreateRelationship $createRelationship) {}

    /**
     * @return array<int, GenealogyWarning>
     */
    public function handle(
        Union $union,
        Person $child,
        ChildRelationshipType $kind = ChildRelationshipType::Biological,
        ?int $birthOrder = null,
    ): array {
        $partnerIds = array_filter([$union->partner_1_id, $union->partner_2_id]);

        if (in_array($child->getKey(), $partnerIds, true)) {
            throw new GenealogyRuleException(
                'A person cannot be their own child in the same union.',
                'CHILD_IS_PARTNER',
            );
        }

        return DB::transaction(function () use ($union, $child, $kind, $birthOrder, $partnerIds): array {
            UnionChild::firstOrCreate(
                ['union_id' => $union->getKey(), 'person_id' => $child->getKey()],
                ['relationship_type' => $kind, 'birth_order' => $birthOrder],
            );

            $subtype = match ($kind) {
                ChildRelationshipType::Adoptive => RelationshipSubtype::Adoptive,
                ChildRelationshipType::Step => RelationshipSubtype::Step,
                ChildRelationshipType::Foster => RelationshipSubtype::Foster,
                default => RelationshipSubtype::Biological,
            };

            $warnings = [];

            foreach ($partnerIds as $parentId) {
                $parent = Person::findOrFail($parentId);

                [, $edgeWarnings] = $this->createRelationship->handle(
                    from: $parent,
                    to: $child,
                    type: RelationshipType::ParentChild,
                    subtype: $subtype,
                    union: $union,
                );

                $warnings = [...$warnings, ...$edgeWarnings];
            }

            return $warnings;
        });
    }
}
