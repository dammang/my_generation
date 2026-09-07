<?php

declare(strict_types=1);

namespace App\Services\Tree;

use App\Enums\Gender;
use App\Models\Person;
use App\Services\Privacy\ViewerScope;

/**
 * How many people descend from somebody, generation by generation.
 *
 * "Seven sons and two daughters, thirty-one grandchildren" is how a family
 * describes itself, and it is the one thing a chart cannot be counted for by
 * hand once it is more than a page.
 *
 * Counted from the graph rather than from what a client happened to fetch:
 * a tree drawn three generations deep would otherwise report a family three
 * generations large, which is the failure this whole application is careful
 * about everywhere else.
 */
class DescendantCensus
{
    /** Deep enough for any real family, bounded so a cycle cannot hang. */
    private const MAX_DEPTH = 20;

    public function __construct(private readonly GraphWalker $walker) {}

    /**
     * @return array{
     *     generations: array<int, array{depth: int, male: int, female: int, unknown: int, total: int}>,
     *     total: int,
     *     hidden: int,
     * }
     */
    public function forPerson(Person $person, ViewerScope $viewer): array
    {
        $depths = $this->walker->descend($person->getKey(), self::MAX_DEPTH);

        // The walk includes the person it started from; they are not their own
        // descendant.
        unset($depths[$person->getKey()]);

        if ($depths === []) {
            return ['generations' => [], 'total' => 0, 'hidden' => 0];
        }

        $people = Person::query()
            ->visibleTo($viewer)
            ->notMerged()
            ->whereIn('id', array_keys($depths))
            ->get(['id', 'gender']);

        $counts = [];

        foreach ($people as $descendant) {
            $depth = $depths[$descendant->getKey()];

            $counts[$depth] ??= ['depth' => $depth, 'male' => 0, 'female' => 0, 'unknown' => 0, 'total' => 0];

            $key = match ($descendant->gender) {
                Gender::Male => 'male',
                Gender::Female => 'female',
                default => 'unknown',
            };

            $counts[$depth][$key]++;
            $counts[$depth]['total']++;
        }

        ksort($counts);

        return [
            'generations' => array_values($counts),
            'total' => $people->count(),
            // Said rather than silently subtracted: a count that quietly
            // excludes the people this reader may not see is a count that
            // disagrees with the one their cousin gets.
            'hidden' => count($depths) - $people->count(),
        ];
    }
}
