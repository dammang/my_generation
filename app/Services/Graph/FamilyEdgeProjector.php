<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Enums\Certainty;
use App\Enums\EdgeKind;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use App\Enums\VerificationStatus;
use App\Models\Person;
use App\Models\Relationship;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the derived `family_edges` adjacency in step with `relationships`.
 *
 * The projection is applied synchronously, not queued. A queued projection
 * would leave the tree stale for exactly as long as the queue is behind, so a
 * contributor who adds a father and taps back to the tree would not see him.
 * The work is one upsert of one or two rows; the bulk
 * `genealogy:rebuild-edges` command exists for repair, not for the write path.
 */
class FamilyEdgeProjector
{
    /** Relationship types that produce a traversable edge at all. */
    private const PROJECTED_TYPES = [
        RelationshipType::ParentChild,
        RelationshipType::Guardian,
    ];

    public function project(Relationship $relationship): void
    {
        if (! $this->isProjectable($relationship)) {
            $this->retract($relationship);

            return;
        }

        DB::table('family_edges')->upsert(
            [[
                'parent_id' => $relationship->person_id,
                'child_id' => $relationship->related_person_id,
                'edge_kind' => $this->edgeKind($relationship)->value,
                'tribe_id' => $this->tribeIdFor($relationship->related_person_id),
                'confidence' => $this->confidence($relationship),
            ]],
            ['parent_id', 'child_id', 'edge_kind'],
            ['tribe_id', 'confidence'],
        );

        // A change of subtype (biological → adoptive) moves the row to a
        // different edge_kind, so any edge left at the old kind is now stale.
        $this->retract($relationship, exceptKind: $this->edgeKind($relationship));
    }

    public function retract(Relationship $relationship, ?EdgeKind $exceptKind = null): void
    {
        DB::table('family_edges')
            ->where('parent_id', $relationship->person_id)
            ->where('child_id', $relationship->related_person_id)
            ->when($exceptKind, fn ($q) => $q->where('edge_kind', '!=', $exceptKind->value))
            ->delete();
    }

    /**
     * Rejected and soft-deleted relationships are excluded from the graph. They
     * are not destroyed — the row survives for the audit trail — but they must
     * not appear in anybody's tree.
     */
    private function isProjectable(Relationship $relationship): bool
    {
        return $relationship->deleted_at === null
            && $relationship->verification_status !== VerificationStatus::Rejected
            && in_array($relationship->relationship_type, self::PROJECTED_TYPES, true);
    }

    private function edgeKind(Relationship $relationship): EdgeKind
    {
        if ($relationship->relationship_type === RelationshipType::Guardian) {
            return EdgeKind::Guardian;
        }

        return EdgeKind::fromSubtype($relationship->relationship_subtype ?? RelationshipSubtype::Unknown);
    }

    private function confidence(Relationship $relationship): int
    {
        return match ($relationship->certainty) {
            Certainty::Proven => 100,
            Certainty::Probable => 75,
            Certainty::Possible => 50,
            default => 25,
        };
    }

    /** Denormalised onto the edge so traversal can scope without a join. */
    private function tribeIdFor(int $personId): ?int
    {
        return Person::withTrashed()->whereKey($personId)->value('tribe_id');
    }
}
