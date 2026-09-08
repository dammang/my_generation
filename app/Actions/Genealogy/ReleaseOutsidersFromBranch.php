<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Models\FamilyBranch;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Lets go of anybody in a branch who does not descend from its founder.
 *
 * A branch is the line descending from one person, so somebody outside that
 * line cannot be in it. Placing was one-directional — it filled empty branches
 * and never emptied a full one — so a branch that had once been given the
 * wrong founder kept everybody that founder had swept in, and went on
 * reporting them as family long after it named somebody else.
 *
 * Only this branch's own people are touched. Somebody in another branch has
 * been put there by an import, a reviewer or by hand, and a founder named over
 * here is not a reason to take them.
 */
class ReleaseOutsidersFromBranch
{
    /** @return int how many people the branch let go */
    public function handle(FamilyBranch $branch): int
    {
        if ($branch->ancestor_person_id === null) {
            // A branch that names no founder makes no claim about who belongs
            // to it, so nobody in it is provably out of place.
            return 0;
        }

        $line = DB::table('lineage_depths')
            ->where('root_person_id', $branch->ancestor_person_id)
            ->pluck('person_id');

        return Person::where('family_branch_id', $branch->getKey())
            ->whereNotIn('id', $line)
            ->update(['family_branch_id' => null]);
    }
}
