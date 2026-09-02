<?php

declare(strict_types=1);

namespace App\Services\Tree;

use App\Models\Person;
use App\Models\Union;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Depth-limited traversal of the genealogy graph.
 *
 * Reads only `family_edges`, whose two covering indexes make every level an
 * index-only scan. The CTEs return ids; a fixed number of hydration queries
 * then loads the payload, so the query count does not grow with the size of the
 * subgraph.
 *
 * No endpoint may return an unbounded graph. Depth AND a node budget are always
 * capped, and truncation drops the furthest generations first — which is what a
 * user expects when they asked for "my family", not "everyone".
 */
class TreeTraversalService
{
    public function __construct(private readonly GraphWalker $walker) {}

    public function build(
        Person $focus,
        int $ancestors,
        int $descendants,
        int $budget,
        bool $includeSpouses = true,
    ): TreeGraph {
        $up = $this->walk($focus->getKey(), $ancestors, direction: 'up');
        $down = $this->walk($focus->getKey(), $descendants, direction: 'down');

        // Negative depth for ancestors, positive for descendants, so a client
        // can lay the graph out in layers without a second pass.
        $depths = [$focus->getKey() => 0];

        foreach ($up as $id => $depth) {
            if ($depth > 0) {
                $depths[$id] = -$depth;
            }
        }

        foreach ($down as $id => $depth) {
            if ($depth > 0) {
                $depths[$id] = $depth;
            }
        }

        $truncated = false;

        if (count($depths) > $budget) {
            $depths = $this->capByDistance($depths, $budget);
            $truncated = true;
        }

        $ids = array_keys($depths);

        $unions = $includeSpouses ? $this->unionsFor($ids) : new Collection;

        // Spouses are derived from unions, so a partner who is not a blood
        // relative still appears — a tree without spouses is not a family tree.
        if ($includeSpouses) {
            $partnerIds = $unions
                ->flatMap(fn (Union $u) => [$u->partner_1_id, $u->partner_2_id])
                ->filter()
                ->reject(fn (int $id) => isset($depths[$id]))
                ->unique();

            foreach ($partnerIds as $partnerId) {
                $depths[$partnerId] = $this->depthOfSpouse($partnerId, $unions, $depths);
            }

            // Spouses are added after the first cap, so the budget is applied
            // again — a stated budget the response quietly exceeds is worse
            // than no budget at all.
            if (count($depths) > $budget) {
                $depths = $this->capByDistance($depths, $budget);
                $truncated = true;
            }

            $ids = array_keys($depths);
        }

        return new TreeGraph(
            focus: $focus,
            people: $this->hydratePeople($ids),
            unions: $unions,
            edges: $this->edgesWithin($ids),
            depths: $depths,
            expandable: $this->expandable($ids),
            ancestorsDepth: $ancestors,
            descendantsDepth: $descendants,
            truncated: $truncated,
            graphVersion: (int) DB::table('tribes')->where('id', $focus->tribe_id)->value('graph_version'),
        );
    }

    /**
     * Keeps the nearest $budget nodes to the focus, which is always retained.
     * Truncation drops the furthest generations first, because that is what
     * somebody who asked for "my family" expects to lose.
     *
     * @param  array<int, int>  $depths
     * @return array<int, int>
     */
    private function capByDistance(array $depths, int $budget): array
    {
        uasort($depths, fn (int $a, int $b) => abs($a) <=> abs($b));

        return array_slice($depths, 0, $budget, true);
    }

    /**
     * @return array<int, int> person id => depth
     */
    private function walk(int $rootId, int $maxDepth, string $direction): array
    {
        if ($maxDepth <= 0) {
            return [$rootId => 0];
        }

        // Node-level BFS rather than a recursive CTE. A CTE with a path guard
        // enumerates every distinct path, and in a DAG the path count grows
        // exponentially with depth while the node count does not: at eight
        // generations down a four-child family that is hundreds of thousands of
        // intermediate rows for a few hundred people. BFS visits each person
        // once, at the cost of one indexed query per level.
        return $direction === 'up'
            ? $this->walker->ascend($rootId, $maxDepth)
            : $this->walker->descend($rootId, $maxDepth);
    }

    /** @param  array<int, int>  $ids */
    private function unionsFor(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Union::query()
            ->where(fn ($q) => $q->whereIn('partner_1_id', $ids)->orWhereIn('partner_2_id', $ids))
            ->with(['childLinks' => fn ($q) => $q->orderBy('birth_order')])
            ->orderBy('order_index')
            ->get();
    }

    /**
     * A spouse sits at the same layer as the partner who brought them into the
     * graph, so couples render side by side.
     *
     * @param  array<int, int>  $depths
     */
    private function depthOfSpouse(int $partnerId, Collection $unions, array $depths): int
    {
        foreach ($unions as $union) {
            $other = $union->partnerOf($partnerId);

            if ($other !== null && isset($depths[$other])) {
                return $depths[$other];
            }
        }

        return 0;
    }

    /** @param  array<int, int>  $ids */
    private function hydratePeople(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        // Every person in the subgraph is loaded, including ones the viewer may
        // not fully see: the mask withholds their content, never their position.
        // Hiding the node would misrepresent everyone else's lineage.
        return Person::query()
            ->whereIn('id', $ids)
            ->with([
                'profileMedia:id,path,conversions',
                'tribe:id,ulid,name',
                'clan:id,ulid,name',
                'generation:id,generation_name',
            ])
            ->get();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{parent: int, child: int, kind: int}>
     */
    private function edgesWithin(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('family_edges')
            ->whereIn('parent_id', $ids)
            ->whereIn('child_id', $ids)
            ->get(['parent_id', 'child_id', 'edge_kind'])
            ->map(fn ($row) => [
                'parent' => (int) $row->parent_id,
                'child' => (int) $row->child_id,
                'kind' => (int) $row->edge_kind,
            ])
            ->all();
    }

    /**
     * How many parents and children each returned node has in total, so the
     * client can draw "+12 more" affordances instead of discovering the
     * boundary by fetching and finding nothing.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{children: int, parents: int}>
     */
    private function expandable(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $children = DB::table('family_edges')
            ->whereIn('parent_id', $ids)
            ->groupBy('parent_id')
            ->pluck(DB::raw('COUNT(*)'), 'parent_id');

        $parents = DB::table('family_edges')
            ->whereIn('child_id', $ids)
            ->groupBy('child_id')
            ->pluck(DB::raw('COUNT(*)'), 'child_id');

        $expandable = [];

        foreach ($ids as $id) {
            $expandable[$id] = [
                'children' => (int) ($children[$id] ?? 0),
                'parents' => (int) ($parents[$id] ?? 0),
            ];
        }

        return $expandable;
    }
}
