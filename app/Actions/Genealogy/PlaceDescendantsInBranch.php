<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Models\FamilyBranch;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * A family branch is the line descending from its founder, so saying who the
 * founder is says who the branch contains.
 *
 * Without this, naming an ancestor computed everybody's depth and changed
 * nothing anybody could see: the generation label is read through the
 * person's own branch, so people who belong to no branch stay unlabelled no
 * matter how much has been counted about them.
 *
 * Only people who belong to no branch yet are placed. Somebody already in one
 * has been put there — by an import, by a reviewer, or by hand — and a
 * newly-named founder is not a reason to move them.
 */
class PlaceDescendantsInBranch
{
    /** @return int how many people the branch gained */
    public function handle(FamilyBranch $branch): int
    {
        if ($branch->ancestor_person_id === null) {
            return 0;
        }

        // lineage_depths already holds exactly this set: everybody reachable
        // downward from the founder, which is the definition of the line.
        $descendants = DB::table('lineage_depths')
            ->where('root_person_id', $branch->ancestor_person_id)
            ->pluck('person_id');

        if ($descendants->isEmpty()) {
            return 0;
        }

        return Person::whereIn('id', $descendants)
            ->whereNull('family_branch_id')
            ->update(['family_branch_id' => $branch->getKey()]);
    }
}
