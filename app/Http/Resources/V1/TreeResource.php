<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\EdgeKind;
use App\Models\Person;
use App\Models\Union;
use App\Models\UnionChild;
use App\Services\Tree\TreeGraph;

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
            'ancestors_depth' => $graph->ancestorsDepth,
            'descendants_depth' => $graph->descendantsDepth,
            'node_count' => $graph->nodeCount(),
            'truncated' => $graph->truncated,
            'graph_version' => $graph->graphVersion,
            'expandable' => $expandable,
        ];
    }
}
