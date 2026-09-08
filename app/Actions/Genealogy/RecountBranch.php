<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Models\FamilyBranch;
use App\Models\Person;

/**
 * Counts a branch's people from the people themselves.
 *
 * The counter is maintained by the person observer, one save at a time, and
 * placing people into a branch is a mass update — which writes the rows and
 * fires no observer. The branch went on displaying whatever the count had
 * been before, which is how one came to say 101 over 283 people without
 * anything being able to notice.
 */
class RecountBranch
{
    /** @return int the count it now holds */
    public function handle(FamilyBranch $branch): int
    {
        $count = Person::where('family_branch_id', $branch->getKey())->count();

        $branch->forceFill(['people_count' => $count])->save();

        return $count;
    }
}
