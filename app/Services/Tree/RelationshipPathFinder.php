<?php

declare(strict_types=1);

namespace App\Services\Tree;

use App\Models\Person;

/**
 * "How am I related to this person?"
 *
 * Finds the nearest common ancestor and names the relationship from the two
 * distances. This is the question people actually open a genealogy app to
 * answer, and it is answerable with two bounded upward walks rather than a
 * search of the whole graph.
 */
class RelationshipPathFinder
{
    private const MAX_DEPTH = 20;

    public function __construct(private readonly GraphWalker $walker) {}

    /**
     * @return array{
     *     related: bool,
     *     label: string|null,
     *     common_ancestor: Person|null,
     *     up: int|null,
     *     down: int|null
     * }
     */
    public function between(Person $from, Person $to): array
    {
        if ($from->is($to)) {
            return $this->result(true, 'the same person', null, 0, 0);
        }

        $fromAncestors = $this->ancestorDepths($from->getKey());
        $toAncestors = $this->ancestorDepths($to->getKey());

        $shared = array_intersect_key($fromAncestors, $toAncestors);

        if ($shared === []) {
            return $this->result(false, null, null, null, null);
        }

        // Nearest common ancestor: the one minimising the total distance, so a
        // shared grandparent beats a shared great-great-grandparent.
        $bestId = null;
        $bestTotal = PHP_INT_MAX;

        foreach (array_keys($shared) as $ancestorId) {
            $total = $fromAncestors[$ancestorId] + $toAncestors[$ancestorId];

            if ($total < $bestTotal) {
                $bestTotal = $total;
                $bestId = $ancestorId;
            }
        }

        $up = $fromAncestors[$bestId];
        $down = $toAncestors[$bestId];

        return $this->result(
            true,
            $this->label($up, $down),
            Person::find($bestId),
            $up,
            $down,
        );
    }

    /**
     * A person counts as their own ancestor at distance 0, so a direct
     * parent-child pair resolves without a special case.
     *
     * @return array<int, int>
     */
    private function ancestorDepths(int $personId): array
    {
        return $this->walker->ascend($personId, self::MAX_DEPTH);
    }

    /** Plain English, from the two distances to the common ancestor. */
    private function label(int $up, int $down): string
    {
        if ($up === 0) {
            return $this->lineal($down, 'descendant');
        }

        if ($down === 0) {
            return $this->lineal($up, 'ancestor');
        }

        if ($up === 1 && $down === 1) {
            return 'sibling';
        }

        // Aunt/uncle and niece/nephew: one side is a direct child of the
        // common ancestor, the other is further down.
        if ($up === 1 || $down === 1) {
            $removed = abs($up - $down);
            $base = $up === 1 ? 'niece or nephew' : 'aunt or uncle';

            return $removed > 1
                ? "great-{$base}".($removed > 2 ? " ({$removed} removed)" : '')
                : $base;
        }

        $degree = min($up, $down) - 1;
        $removed = abs($up - $down);

        $ordinal = match ($degree) {
            1 => 'first',
            2 => 'second',
            3 => 'third',
            default => "{$degree}th",
        };

        return $removed === 0
            ? "{$ordinal} cousin"
            : "{$ordinal} cousin ".($removed === 1 ? 'once' : ($removed === 2 ? 'twice' : "{$removed} times")).' removed';
    }

    private function lineal(int $distance, string $direction): string
    {
        return match ($distance) {
            1 => $direction === 'ancestor' ? 'parent' : 'child',
            2 => $direction === 'ancestor' ? 'grandparent' : 'grandchild',
            3 => $direction === 'ancestor' ? 'great-grandparent' : 'great-grandchild',
            default => str_repeat('great-', $distance - 2)
                .($direction === 'ancestor' ? 'grandparent' : 'grandchild'),
        };
    }

    /** @return array<string, mixed> */
    private function result(bool $related, ?string $label, ?Person $ancestor, ?int $up, ?int $down): array
    {
        return [
            'related' => $related,
            'label' => $label,
            'common_ancestor' => $ancestor,
            'up' => $up,
            'down' => $down,
        ];
    }
}
