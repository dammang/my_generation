<?php

declare(strict_types=1);

namespace App\Actions\Genealogy;

use App\Exceptions\CycleDetectedException;
use App\Models\Person;
use App\Services\Tree\CycleGuard;
use Illuminate\Support\Facades\DB;

/**
 * Refuses a parent-child edge that would make somebody their own ancestor.
 *
 * One of the very few hard errors in this system. Almost every other
 * implausibility is a warning, because historical records are wrong and
 * blocking a contributor loses the data forever. A cycle is different: it makes
 * every traversal below it *incorrect* rather than merely doubtful, and it is
 * the one condition that could turn a depth-limited query unbounded if the
 * path guard in the CTE ever failed.
 */
class AssertNoCycle
{
    /** Deep enough for any real pedigree, bounded so a corrupt graph cannot hang. */
    private const MAX_WALK = 64;

    public function handle(int $parentId, int $childId): void
    {
        if ($parentId === $childId) {
            throw new CycleDetectedException([
                Person::withTrashed()->whereKey($parentId)->value('display_name') ?? 'This person',
            ]);
        }

        // Walk upward from the proposed parent looking for the proposed child.
        // If the child is already an ancestor of the parent, the new edge would
        // close a loop.
        $length = CycleGuard::pathLength(self::MAX_WALK);

        $rows = DB::select(<<<SQL
            WITH RECURSIVE anc (person_id, depth, path) AS (
                SELECT ?, 0, CAST(? AS CHAR({$length}))
                UNION ALL
                SELECT fe.parent_id, a.depth + 1, CONCAT(a.path, ',', fe.parent_id)
                FROM anc a
                JOIN family_edges fe ON fe.child_id = a.person_id
                WHERE a.depth < ?
                  AND FIND_IN_SET(fe.parent_id, a.path) = 0
            )
            SELECT path FROM anc WHERE person_id = ? LIMIT 1
        SQL, [$parentId, $parentId, self::MAX_WALK, $childId]);

        if ($rows === []) {
            return;
        }

        throw new CycleDetectedException($this->describe($rows[0]->path));
    }

    /**
     * Names along the offending loop, so the contributor is told which
     * relationship is the problem rather than just that one exists.
     *
     * @return array<int, string>
     */
    private function describe(string $path): array
    {
        $ids = array_map('intval', array_filter(explode(',', $path)));

        $names = Person::withTrashed()
            ->whereIn('id', $ids)
            ->pluck('display_name', 'id');

        $trail = array_map(
            static fn (int $id) => $names[$id] ?? "#{$id}",
            $ids,
        );

        // Close the loop back to where the walk started: the proposed edge is
        // what would join the last name to the first.
        if ($trail !== []) {
            $trail[] = $trail[0];
        }

        return $trail;
    }
}
