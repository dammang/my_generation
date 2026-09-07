<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Models\FamilyBranch;
use App\Models\Person;
use App\Services\Tree\LineageDepthService;
use Illuminate\Support\Facades\DB;

/**
 * Everything that has to happen when a family branch is told where it starts.
 *
 * Three things, and each is invisible on its own:
 *
 *   * the descendants are counted from the founder, so anybody has a number;
 *   * the founder's own number on the clan's older scale is recorded, so the
 *     two reckonings can be shown together — "11th generation from Pu Zo,
 *     1st generation of Jasuan" is how a family actually says it;
 *   * the people descended from the founder are placed in the branch, because
 *     the number is read through a person's own branch.
 */
class AnchorFamilyBranch
{
    public function __construct(
        private readonly LineageDepthService $depths,
        private readonly PlaceDescendantsInBranch $place,
    ) {}

    /** @return int how many people the branch gained */
    public function handle(FamilyBranch $branch): int
    {
        $ancestor = $branch->ancestor_person_id === null
            ? null
            : Person::find($branch->ancestor_person_id);

        if ($ancestor === null) {
            // A branch that no longer names a founder sits on no scale.
            $branch->forceFill(['generation_offset' => null])->save();

            return 0;
        }

        $this->depths->recomputeFor($ancestor);

        $branch->forceFill(['generation_offset' => $this->offsetFor($branch)])->save();

        return $this->place->handle($branch);
    }

    /**
     * Which generation the branch's founder is, counted from the clan's own
     * ancestor.
     *
     * Null when the clan names no ancestor, or when the founder does not
     * descend from them — in which case there is no shared scale, and
     * inventing a number for one would be worse than showing none.
     */
    private function offsetFor(FamilyBranch $branch): ?int
    {
        $clanAncestorId = $branch->clan?->ancestor_person_id;

        if ($clanAncestorId === null) {
            return null;
        }

        if ($clanAncestorId === $branch->ancestor_person_id) {
            return 1;
        }

        $this->ensureDepthsFor($clanAncestorId);

        $depth = DB::table('lineage_depths')
            ->where('root_person_id', $clanAncestorId)
            ->where('person_id', $branch->ancestor_person_id)
            ->value('depth');

        return $depth === null ? null : (int) $depth + 1;
    }

    /**
     * The clan's ancestor is a root like any other, but nothing has ever asked
     * for their depths: only branch founders were computed. Without them the
     * older scale does not exist and every branch reports no offset.
     */
    private function ensureDepthsFor(int $rootId): void
    {
        $known = DB::table('lineage_depths')->where('root_person_id', $rootId)->exists();

        if ($known) {
            return;
        }

        $root = Person::find($rootId);

        if ($root !== null) {
            $this->depths->recomputeFor($root);
        }
    }
}
