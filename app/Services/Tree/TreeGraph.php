<?php

declare(strict_types=1);

namespace App\Services\Tree;

use App\Models\Person;
use App\Models\Union;
use Illuminate\Support\Collection;

/**
 * The result of one traversal: nodes, the couples that group them, the edges
 * between them, and where the client may expand next.
 *
 * A projection, never persisted. The same person appears in thousands of
 * different graphs depending on who is at the root.
 */
final readonly class TreeGraph
{
    /**
     * @param  Collection<int, Person>  $people
     * @param  Collection<int, Union>  $unions
     * @param  array<int, array{parent: int, child: int, kind: int}>  $edges
     * @param  array<int, int>  $depths  person id => depth relative to the focus
     * @param  array<int, array{children: int, parents: int}>  $expandable
     */
    /**
     * How far the graph actually reaches, rather than how far was asked for.
     *
     * ancestorsDepth and descendantsDepth are the request's parameters. The
     * chart reported them as "3 up, 2 down", which describes the query and not
     * the family: a person with no children was told there were two
     * generations below them.
     */
    public function reachedAbove(): int
    {
        return $this->depths === [] ? 0 : max(0, -min($this->depths));
    }

    public function reachedBelow(): int
    {
        return $this->depths === [] ? 0 : max(0, max($this->depths));
    }

    public function __construct(
        public Person $focus,
        public Collection $people,
        public Collection $unions,
        public array $edges,
        public array $depths,
        public array $expandable,
        public int $ancestorsDepth,
        public int $descendantsDepth,
        public bool $truncated,
        public int $graphVersion,
    ) {}

    public function nodeCount(): int
    {
        return $this->people->count();
    }
}
