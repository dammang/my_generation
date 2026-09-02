<?php

declare(strict_types=1);

namespace App\Services\Graph;

/**
 * A switch for the per-row side effects model observers perform: edge
 * projection, graph-version bumps and denormalised counters.
 *
 * Bulk work — a GEDCOM import, a merge, a rebuild — should do these once at the
 * end in set-based SQL rather than a hundred thousand times row by row. Wrap
 * that work in `GraphSideEffects::without(...)` and follow it with
 * `genealogy:rebuild-edges` plus a counter recount.
 *
 * This is deliberately not a queue: the write path stays synchronous so a
 * contributor who adds a father sees him in the tree immediately.
 */
class GraphSideEffects
{
    private static bool $enabled = true;

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function without(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }
}
