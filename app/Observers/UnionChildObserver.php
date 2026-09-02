<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UnionChild;
use App\Services\Graph\GraphSideEffects;
use Illuminate\Support\Facades\DB;

/**
 * Keeps unions.children_count and birth_order consistent.
 *
 * children_count is denormalised because the tree renders "+N more children"
 * affordances for every node in a subgraph, and counting per node would be one
 * query per card.
 */
class UnionChildObserver
{
    public function creating(UnionChild $link): void
    {
        if ($link->birth_order !== null) {
            return;
        }

        $link->birth_order = (int) UnionChild::where('union_id', $link->union_id)->max('birth_order') + 1;
    }

    public function created(UnionChild $link): void
    {
        $this->recount($link);
    }

    public function deleted(UnionChild $link): void
    {
        $this->recount($link);
    }

    /**
     * Recomputed rather than incremented: an increment that runs twice, or not
     * at all, leaves a count nobody notices is wrong until a chart looks odd.
     */
    private function recount(UnionChild $link): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        DB::table('unions')->where('id', $link->union_id)->update([
            'children_count' => DB::table('union_children')->where('union_id', $link->union_id)->count(),
        ]);
    }
}
