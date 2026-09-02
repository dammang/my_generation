<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Regenerates the derived `family_edges` adjacency table from the source of
 * truth (`relationships`).
 *
 * family_edges is a cache, not truth: it exists so tree traversal reads a lean
 * five-column table with covering indexes instead of dragging 25 columns,
 * soft-delete predicates and status filters through a recursive CTE at depth 8.
 * Because it is derived, it can always be thrown away and rebuilt — which is
 * exactly what this command is for, after an import, a merge, or a bug.
 */
class RebuildFamilyEdges extends Command
{
    protected $signature = 'genealogy:rebuild-edges
                            {--tribe= : Limit the rebuild to one tribe id}
                            {--fresh : Truncate before rebuilding instead of upserting}';

    protected $description = 'Rebuild the derived family_edges traversal table from relationships';

    public function handle(): int
    {
        $tribeId = $this->option('tribe');

        if ($this->option('fresh')) {
            if ($tribeId) {
                DB::table('family_edges')->where('tribe_id', $tribeId)->delete();
                $this->line("Cleared edges for tribe {$tribeId}.");
            } else {
                DB::statement('DELETE FROM family_edges');
                $this->line('Cleared all edges.');
            }
        }

        $bindings = [];
        $tribeFilter = '';

        if ($tribeId) {
            $tribeFilter = ' AND child.tribe_id = ?';
            $bindings[] = $tribeId;
        }

        // Parent-child edges. edge_kind and confidence are projected from the
        // subtype and certainty so the traversal never needs to join back.
        $parentChild = DB::affectingStatement(<<<SQL
            INSERT INTO family_edges (parent_id, child_id, edge_kind, tribe_id, confidence)
            SELECT
                r.person_id,
                r.related_person_id,
                CASE r.relationship_subtype
                    WHEN 'adoptive' THEN 2
                    WHEN 'step'     THEN 3
                    WHEN 'foster'   THEN 4
                    ELSE 1
                END,
                child.tribe_id,
                CASE r.certainty
                    WHEN 'proven'   THEN 100
                    WHEN 'probable' THEN 75
                    WHEN 'possible' THEN 50
                    ELSE 25
                END
            FROM relationships r
            JOIN people child ON child.id = r.related_person_id AND child.deleted_at IS NULL
            JOIN people parent ON parent.id = r.person_id AND parent.deleted_at IS NULL
            WHERE r.deleted_at IS NULL
              AND r.relationship_type = 'parent_child'
              AND r.verification_status <> 'rejected'
              {$tribeFilter}
            ON DUPLICATE KEY UPDATE
                tribe_id   = VALUES(tribe_id),
                confidence = VALUES(confidence)
        SQL, $bindings);

        $guardian = DB::affectingStatement(<<<SQL
            INSERT INTO family_edges (parent_id, child_id, edge_kind, tribe_id, confidence)
            SELECT r.person_id, r.related_person_id, 5, child.tribe_id, 50
            FROM relationships r
            JOIN people child ON child.id = r.related_person_id AND child.deleted_at IS NULL
            JOIN people parent ON parent.id = r.person_id AND parent.deleted_at IS NULL
            WHERE r.deleted_at IS NULL
              AND r.relationship_type = 'guardian'
              AND r.verification_status <> 'rejected'
              {$tribeFilter}
            ON DUPLICATE KEY UPDATE
                tribe_id   = VALUES(tribe_id),
                confidence = VALUES(confidence)
        SQL, $bindings);

        // Remove edges whose source relationship has since been deleted or rejected.
        $stale = DB::affectingStatement(<<<'SQL'
            DELETE fe FROM family_edges fe
            LEFT JOIN relationships r
                   ON r.person_id = fe.parent_id
                  AND r.related_person_id = fe.child_id
                  AND r.deleted_at IS NULL
                  AND r.verification_status <> 'rejected'
                  AND ((fe.edge_kind = 5 AND r.relationship_type = 'guardian')
                    OR (fe.edge_kind < 5 AND r.relationship_type = 'parent_child'))
            WHERE r.id IS NULL
        SQL);

        $total = DB::table('family_edges')->count();

        $this->info("Parent-child edges written: {$parentChild}");
        $this->info("Guardian edges written:     {$guardian}");
        $this->info("Stale edges removed:        {$stale}");
        $this->info("Total edges now:            {$total}");

        return self::SUCCESS;
    }
}
