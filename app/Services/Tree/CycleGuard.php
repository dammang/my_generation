<?php

declare(strict_types=1);

namespace App\Services\Tree;

/**
 * Sizing for the path column that guards recursive traversals against cycles.
 *
 * Every recursive CTE here carries an accumulated path of visited ids so a
 * corrupted edge cannot turn a bounded query unbounded. The column's *declared*
 * width is what matters for cost: MySQL materialises the CTE into the InnoDB
 * temporary tablespace and allocates the declared width per row, not the actual
 * string length.
 *
 * A CHAR(4000) guard on a 100k-person graph grew the temp tablespace to 11GB
 * during a benchmark — the traversal was correct, but each of hundreds of
 * thousands of intermediate rows reserved four kilobytes for a path that never
 * exceeded a hundred characters. Sizing it to the depth cap is the difference
 * between a scratch buffer and filling the disk.
 */
final class CycleGuard
{
    /** Widest plausible id plus its separator. */
    private const BYTES_PER_ID = 12;

    /** Never narrower than this, so a shallow query still has slack. */
    private const MINIMUM = 64;

    public static function pathLength(int $maxDepth): int
    {
        return max(self::MINIMUM, ($maxDepth + 1) * self::BYTES_PER_ID);
    }
}
