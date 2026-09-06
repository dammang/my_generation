<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\EdgeKind;
use App\Models\Person;
use App\Models\Union;
use App\Models\UnionChild;
use App\Services\Privacy\ViewerScope;
use App\Services\Tree\TreeGraph;
use Illuminate\Support\Facades\DB;

/**
 * Serialises a traversal into the shape a layered chart needs.
 *
 * `depth` gives the layer directly, `unions[].children` is already in birth
 * order, and `edges[].kind` tells the client which connectors to draw dashed.
 * The client lays the graph out; the server never stores a rendered tree.
 */
class TreeResource
{
    /** @return array<string, mixed> */
    public static function make(TreeGraph $graph): array
    {
        $ulids = $graph->people->pluck('ulid', 'id');

        return [
            'focus' => $graph->focus->ulid,
            'people' => $graph->people
                ->map(fn (Person $person) => [
                    ...PersonResource::make($person)->resolve(),
                    'depth' => $graph->depths[$person->getKey()] ?? 0,
                ])
                ->sortBy('depth')
                ->values()
                ->all(),
            'unions' => $graph->unions
                ->map(fn (Union $union) => [
                    'ulid' => $union->ulid,
                    'partners' => array_values(array_filter([
                        $ulids[$union->partner_1_id] ?? null,
                        $ulids[$union->partner_2_id] ?? null,
                    ])),
                    'children' => $union->childLinks
                        ->map(fn (UnionChild $link) => $ulids[$link->person_id] ?? null)
                        ->filter()
                        ->values()
                        ->all(),
                    'union_type' => $union->union_type->value,
                    'status' => $union->status->value,
                    'marriage_year' => $union->marriage_year,
                    'order_index' => $union->order_index,
                ])
                ->values()
                ->all(),
            'edges' => collect($graph->edges)
                ->filter(fn (array $edge) => isset($ulids[$edge['parent']], $ulids[$edge['child']]))
                ->map(fn (array $edge) => [
                    'parent' => $ulids[$edge['parent']],
                    'child' => $ulids[$edge['child']],
                    'kind' => (EdgeKind::tryFrom($edge['kind']) ?? EdgeKind::Biological)->slug(),
                    'dashed' => EdgeKind::tryFrom($edge['kind'])?->isDashed() ?? false,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */

    /**
     * How large the family actually is, and where the focus sits within it.
     *
     * Counted through the viewer's own scope, so this never reports people
     * somebody is not allowed to know exist.
     *
     * @return array{people: int, above: int, below: int}
     */
    private static function clanTotals(Person $focus): array
    {
        $viewer = app(ViewerScope::class);

        $people = Person::query()
            ->visibleTo($viewer)
            ->notMerged()
            ->when(
                $focus->clan_id !== null,
                fn ($q) => $q->where('clan_id', $focus->clan_id),
                fn ($q) => $q->where('tribe_id', $focus->tribe_id),
            )
            ->count();

        $root = DB::table('family_branches')
            ->where('id', $focus->family_branch_id)
            ->value('ancestor_person_id');

        if ($root === null) {
            return ['people' => $people, 'above' => 0, 'below' => 0];
        }

        $depths = DB::table('lineage_depths')->where('root_person_id', $root);

        // Depth is measured from the apical ancestor, so the focus's own depth
        // is how many generations sit above it, and whatever is left of the
        // deepest line is how many sit below.
        $here = (int) ((clone $depths)->where('person_id', $focus->getKey())->value('depth') ?? 0);
        $deepest = (int) ((clone $depths)->max('depth') ?? 0);

        return [
            'people' => $people,
            'above' => $here,
            'below' => max(0, $deepest - $here),
        ];
    }

    public static function meta(TreeGraph $graph): array
    {
        $ulids = $graph->people->pluck('ulid', 'id');

        // Counted in one pass over the edges. Scanning the full edge list once
        // per node is O(nodes × edges) — with a few hundred people and a
        // thousand edges that is half a million collection operations, and it
        // dominated the response time before the graph did.
        $shownChildren = [];
        $shownParents = [];

        foreach ($graph->edges as $edge) {
            if (! isset($ulids[$edge['parent']], $ulids[$edge['child']])) {
                continue;
            }

            $shownChildren[$edge['parent']] = ($shownChildren[$edge['parent']] ?? 0) + 1;
            $shownParents[$edge['child']] = ($shownParents[$edge['child']] ?? 0) + 1;
        }

        $expandable = [];

        foreach ($graph->expandable as $personId => $counts) {
            $ulid = $ulids[$personId] ?? null;

            if ($ulid === null) {
                continue;
            }

            // Only report what is NOT already in the payload — otherwise every
            // node looks expandable and the UI draws affordances that do
            // nothing when tapped.
            $hiddenChildren = max(0, $counts['children'] - ($shownChildren[$personId] ?? 0));
            $hiddenParents = max(0, $counts['parents'] - ($shownParents[$personId] ?? 0));

            if ($hiddenChildren > 0 || $hiddenParents > 0) {
                $expandable[$ulid] = array_filter([
                    'children' => $hiddenChildren,
                    'parents' => $hiddenParents,
                ]);
            }
        }

        return [
            // What was asked for, kept because the client uses it to decide
            // whether asking for more would return anything new.
            'ancestors_depth' => $graph->ancestorsDepth,
            'descendants_depth' => $graph->descendantsDepth,

            // What is actually in this graph. The chart shows these, because
            // "2 down" under somebody with no children is a statement about
            // the request and reads as a statement about the family.
            'reached_above' => $graph->reachedAbove(),
            'reached_below' => $graph->reachedBelow(),

            // The family, rather than the window onto it. The chart is a
            // few generations of a much larger thing, and "14 people" said
            // about what had been fetched reads as a statement about how
            // many relatives somebody has.
            'clan' => self::clanTotals($graph->focus),
            'node_count' => $graph->nodeCount(),
            'truncated' => $graph->truncated,
            'graph_version' => $graph->graphVersion,
            'expandable' => $expandable,
        ];
    }
}
