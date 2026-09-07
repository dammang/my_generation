<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Enums\ChildRelationshipType;
use App\Exceptions\GenealogyRuleException;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Union;
use App\Models\UnionChild;
use App\Services\Integrity\GenealogyWarning;
use Illuminate\Support\Facades\DB;

/**
 * Moves a child from one marriage to another.
 *
 * A man with two wives has two sets of children, and which set a child belongs
 * to is a fact about their mother — the commonest correction there is, and one
 * nobody could make without deleting the child and entering them again.
 *
 * Both halves in one transaction. Detaching and re-attaching through the two
 * existing endpoints would leave a child with no parents at all if the second
 * call never arrived, and on a phone that is not a hypothetical.
 */
class MoveChildBetweenUnions
{
    public function __construct(private readonly AddChildToUnion $addChild) {}

    /**
     * @return array<int, GenealogyWarning>
     */
    public function handle(Union $from, Union $to, Person $child): array
    {
        if ($from->is($to)) {
            return [];
        }

        $link = UnionChild::where('union_id', $from->getKey())
            ->where('person_id', $child->getKey())
            ->first();

        if ($link === null) {
            throw new GenealogyRuleException(
                'That child does not belong to this marriage.',
                'CHILD_NOT_IN_UNION',
            );
        }

        $this->assertRelated($from, $to);

        return DB::transaction(function () use ($from, $to, $child, $link): array {
            // How they joined the family travels with them: an adopted child
            // moved between two of the same father's marriages is still
            // adopted, and re-deriving that would quietly lose it.
            $kind = $link->relationship_type ?? ChildRelationshipType::Biological;
            $order = $link->birth_order;

            $link->delete();

            // The edges belonging to the old marriage go with it. Leaving them
            // would assert both mothers at once, which is the thing being
            // corrected.
            Relationship::where('union_id', $from->getKey())
                ->where('related_person_id', $child->getKey())
                ->get()
                ->each(fn (Relationship $relationship) => $relationship->delete());

            return $this->addChild->handle($to, $child, $kind, $order);
        });
    }

    /**
     * The two marriages have to share a partner.
     *
     * Moving a child between one father's marriages corrects which mother they
     * had. Moving them to a couple with nobody in common is not a correction,
     * it is a different claim about who they are — and it should be made by
     * saying so, not by a menu item meant for the first thing.
     */
    private function assertRelated(Union $from, Union $to): void
    {
        $shared = array_intersect(
            array_filter([$from->partner_1_id, $from->partner_2_id]),
            array_filter([$to->partner_1_id, $to->partner_2_id]),
        );

        if ($shared === []) {
            throw new GenealogyRuleException(
                'Those two marriages have nobody in common, so this would be a '
                .'claim about who the child is rather than which marriage they '
                .'belong to.',
                'UNIONS_UNRELATED',
            );
        }
    }
}
