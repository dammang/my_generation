<?php

declare(strict_types=1);

namespace App\Services\Tree;

use Illuminate\Support\Facades\DB;

/**
 * Level-by-level traversal that visits each node once.
 *
 * A recursive CTE with a path guard enumerates every distinct *path*, not every
 * distinct node. In a genealogy that is a DAG — two parents per person, lines
 * re-converging whenever cousins marry — the number of paths grows
 * exponentially with depth even though the number of people does not. At depth
 * 64 over a 100k-person graph that is not a slow query; it is one that fills the
 * disk with temporary tablespace.
 *
 * Bounded traversals (the tree endpoint, depth ≤ 8) still use CTEs, where the
 * path set is small and one round trip beats several. Deep ones use this: each
 * level is a single indexed query, and a node already seen is never expanded
 * again.
 */
class GraphWalker
{
    /** How many ids to put in one IN clause. */
    private const CHUNK = 2000;

    /**
     * Shortest distance from $rootId to every reachable node.
     *
     * @return array<int, int> person id => depth
     */
    public function descend(int $rootId, int $maxDepth): array
    {
        return $this->walk($rootId, $maxDepth, 'parent_id', 'child_id');
    }

    /**
     * @return array<int, int> person id => depth
     */
    public function ascend(int $rootId, int $maxDepth): array
    {
        return $this->walk($rootId, $maxDepth, 'child_id', 'parent_id');
    }

    /**
     * Longest distance from the root to each node, by relaxing along a
     * topological order of the reachable subgraph.
     *
     * Needed because min and max genuinely differ under pedigree collapse: a
     * person can be 14 generations from the founder down one line and 16 down
     * another, and reporting a single number would be a guess.
     *
     * @param  array<int, int>  $minDepths  from descend()
     * @return array<int, int> person id => longest depth
     */
    public function longestDescent(int $rootId, array $minDepths): array
    {
        $ids = array_keys($minDepths);
        $edges = $this->edgesWithin($ids);

        $indegree = array_fill_keys($ids, 0);
        $children = [];

        foreach ($edges as [$parent, $child]) {
            $children[$parent][] = $child;
            $indegree[$child]++;
        }

        $max = array_fill_keys($ids, 0);
        $max[$rootId] = 0;

        // Kahn's algorithm. The subgraph is acyclic by construction — cycles
        // are refused on write and guarded on read — so every node is emitted.
        $queue = [];
        foreach ($indegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        while ($queue !== []) {
            $node = array_shift($queue);

            foreach ($children[$node] ?? [] as $child) {
                if ($max[$child] < $max[$node] + 1) {
                    $max[$child] = $max[$node] + 1;
                }

                if (--$indegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        return $max;
    }

    /**
     * @return array<int, int> person id => depth
     */
    private function walk(int $rootId, int $maxDepth, string $from, string $to): array
    {
        $seen = [$rootId => 0];
        $frontier = [$rootId];

        for ($depth = 1; $depth <= $maxDepth && $frontier !== []; $depth++) {
            $next = [];

            foreach (array_chunk($frontier, self::CHUNK) as $chunk) {
                $rows = DB::table('family_edges')
                    ->whereIn($from, $chunk)
                    ->distinct()
                    ->pluck($to);

                foreach ($rows as $id) {
                    $id = (int) $id;

                    // Already seen means already reached by a shorter or equal
                    // path — expanding it again is what the CTE was doing.
                    if (! isset($seen[$id])) {
                        $seen[$id] = $depth;
                        $next[] = $id;
                    }
                }
            }

            $frontier = $next;
        }

        return $seen;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{0: int, 1: int}>
     */
    private function edgesWithin(array $ids): array
    {
        $lookup = array_flip($ids);
        $edges = [];

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            foreach (DB::table('family_edges')->whereIn('parent_id', $chunk)->get(['parent_id', 'child_id']) as $row) {
                $child = (int) $row->child_id;

                if (isset($lookup[$child])) {
                    $edges[] = [(int) $row->parent_id, $child];
                }
            }
        }

        return $edges;
    }
}
