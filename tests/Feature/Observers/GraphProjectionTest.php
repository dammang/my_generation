<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Enums\EdgeKind;
use App\Enums\RelationshipSubtype;
use App\Enums\VerificationStatus;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Tribe;
use App\Services\Graph\GraphSideEffects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The projection runs synchronously on write, so a contributor who adds a
 * father sees him in the tree immediately rather than whenever a queue drains.
 */
class GraphProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_relationship_projects_an_edge_without_a_rebuild(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();

        Relationship::factory()->parentChild($parent, $child)->create();

        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'edge_kind' => EdgeKind::Biological->value,
        ]);
    }

    public function test_soft_deleting_a_relationship_retracts_its_edge(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();
        $relationship = Relationship::factory()->parentChild($parent, $child)->create();

        $relationship->delete();

        $this->assertSame(0, DB::table('family_edges')->count());
        // The row itself survives for the audit trail.
        $this->assertSoftDeleted('relationships', ['id' => $relationship->id]);
    }

    public function test_restoring_a_relationship_returns_its_edge(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();
        $relationship = Relationship::factory()->parentChild($parent, $child)->create();

        $relationship->delete();
        $relationship->restore();

        $this->assertSame(1, DB::table('family_edges')->count());
    }

    public function test_rejecting_a_relationship_removes_it_from_the_graph(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();
        $relationship = Relationship::factory()->parentChild($parent, $child)->create();

        $relationship->update(['verification_status' => VerificationStatus::Rejected]);

        $this->assertSame(0, DB::table('family_edges')->count());
    }

    public function test_changing_the_subtype_moves_the_edge_to_the_new_kind(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();
        $relationship = Relationship::factory()->parentChild($parent, $child)->create();

        $relationship->update(['relationship_subtype' => RelationshipSubtype::Adoptive]);

        $this->assertSame(1, DB::table('family_edges')->count());
        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'edge_kind' => EdgeKind::Adoptive->value,
        ]);
    }

    public function test_a_genealogy_write_bumps_the_tribes_graph_version(): void
    {
        // One integer invalidates every cached subtree in the tribe, instead of
        // walking the graph to discover which caches contained the change.
        $tribe = Tribe::factory()->create();
        $parent = Person::factory()->create(['tribe_id' => $tribe->id]);
        $child = Person::factory()->create(['tribe_id' => $tribe->id]);

        $before = $tribe->fresh()->graph_version;
        Relationship::factory()->parentChild($parent, $child)->create();

        $this->assertGreaterThan($before, $tribe->fresh()->graph_version);
    }

    public function test_side_effects_can_be_suspended_for_bulk_imports(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();

        GraphSideEffects::without(function () use ($parent, $child): void {
            Relationship::factory()->parentChild($parent, $child)->create();
        });

        $this->assertSame(0, DB::table('family_edges')->count());

        // The rebuild command is how bulk work catches up afterwards.
        $this->artisan('genealogy:rebuild-edges')->assertSuccessful();

        $this->assertSame(1, DB::table('family_edges')->count());
    }
}
