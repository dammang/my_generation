<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Relationship;
use App\Services\Graph\FamilyEdgeProjector;
use App\Services\Graph\GraphSideEffects;
use App\Services\Graph\GraphVersion;

/**
 * Keeps the derived traversal table and the tree cache honest.
 *
 * This lives in an observer rather than in the actions that write
 * relationships, because a relationship can also be created by Filament, a
 * seeder, an importer or a change-request application. Anywhere the row
 * appears, the edge must follow it.
 */
class RelationshipObserver
{
    public function __construct(
        private readonly FamilyEdgeProjector $projector,
        private readonly GraphVersion $graphVersion,
    ) {}

    public function created(Relationship $relationship): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        $this->projector->project($relationship);
        $this->bump($relationship);
    }

    public function updated(Relationship $relationship): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        // Only re-project when something the edge actually carries has moved.
        $projected = ['relationship_type', 'relationship_subtype', 'certainty', 'verification_status'];

        if ($relationship->wasChanged($projected)) {
            $this->projector->project($relationship);
        }

        $this->bump($relationship);
    }

    public function deleted(Relationship $relationship): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        // The row survives soft-deleted for the audit trail, but it must leave
        // the graph immediately.
        $this->projector->retract($relationship);
        $this->bump($relationship);
    }

    public function restored(Relationship $relationship): void
    {
        if (! GraphSideEffects::enabled()) {
            return;
        }

        $this->projector->project($relationship);
        $this->bump($relationship);
    }

    private function bump(Relationship $relationship): void
    {
        $this->graphVersion->bumpMany(
            $relationship->newQuery()
                ->getConnection()
                ->table('people')
                ->whereIn('id', [$relationship->person_id, $relationship->related_person_id])
                ->pluck('tribe_id')
                ->all()
        );
    }
}
