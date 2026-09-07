<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Models\Clan;
use App\Models\Person;
use App\Services\Tree\LineageDepthService;
use Illuminate\Support\Facades\DB;

/**
 * Puts a clan's generation numbering on both of its scales.
 *
 * A clan counts from somebody recent enough that the numbers mean something —
 * Jasuan — while still descending from somebody much older — Pu Zo. This
 * computes the depths under each, and records where the origin falls on the
 * older scale, so a person can be shown as both the first generation of Jasuan
 * and the eleventh from Pu Zo without either number being worked out per
 * request.
 */
class AnchorClanGenerations
{
    public function __construct(private readonly LineageDepthService $depths) {}

    public function handle(Clan $clan): void
    {
        foreach ([$clan->ancestor_person_id, $clan->counting_origin_person_id] as $rootId) {
            if ($rootId === null) {
                continue;
            }

            $root = Person::find($rootId);

            if ($root !== null) {
                $this->depths->recomputeFor($root);
            }
        }

        $clan->forceFill(['generation_offset' => $this->offsetFor($clan)])->save();
    }

    /**
     * Which generation the counting origin is, on the clan's older scale.
     *
     * Null when there is no older scale, or when the origin does not descend
     * from the older ancestor at all — two families the archive cannot yet
     * connect, where inventing a number would assert a descent nobody recorded.
     */
    private function offsetFor(Clan $clan): ?int
    {
        $origin = $clan->counting_origin_person_id;
        $older = $clan->ancestor_person_id;

        if ($origin === null || $older === null) {
            return null;
        }

        if ($origin === $older) {
            return 1;
        }

        $depth = DB::table('lineage_depths')
            ->where('root_person_id', $older)
            ->where('person_id', $origin)
            ->value('depth');

        return $depth === null ? null : (int) $depth + 1;
    }
}
