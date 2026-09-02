<?php

declare(strict_types=1);

namespace App\Services\Graph;

use Illuminate\Support\Facades\DB;

/**
 * Cache invalidation for the tree, in one integer per tribe.
 *
 * Every cached subtree key contains the tribe's graph_version. Bumping it
 * invalidates every cached tree in that tribe at once, instead of hunting down
 * which of thousands of cached subgraphs happened to contain the person who
 * changed — which is not knowable without walking the graph, i.e. doing the
 * expensive thing the cache exists to avoid.
 *
 * The cost of over-invalidating is a cold cache for one tribe. The cost of
 * under-invalidating is showing somebody a family tree that is wrong.
 */
class GraphVersion
{
    public function bump(?int $tribeId): void
    {
        if ($tribeId === null) {
            return;
        }

        DB::table('tribes')->where('id', $tribeId)->increment('graph_version');
    }

    /** @param  iterable<int|null>  $tribeIds */
    public function bumpMany(iterable $tribeIds): void
    {
        $ids = array_values(array_unique(array_filter(iterator_to_array($tribeIds))));

        if ($ids === []) {
            return;
        }

        DB::table('tribes')->whereIn('id', $ids)->increment('graph_version');
    }

    public function current(int $tribeId): int
    {
        return (int) DB::table('tribes')->where('id', $tribeId)->value('graph_version');
    }
}
