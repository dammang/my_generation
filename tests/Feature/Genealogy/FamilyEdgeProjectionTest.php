<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Enums\EdgeKind;
use App\Models\Person;
use App\Models\Relationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * family_edges is a derived cache, so the one property that matters is that it
 * can always be regenerated from the source of truth and agrees with it.
 */
class FamilyEdgeProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_parent_child_relationships_into_edges(): void
    {
        $father = Person::factory()->male()->bornExactly(1898)->deceased(1961)->create();
        $mother = Person::factory()->female()->bornExactly(1902)->deceased(1979)->create();
        $child = Person::factory()->bornExactly(1926)->create();

        Relationship::factory()->parentChild($father, $child)->create();
        Relationship::factory()->parentChild($mother, $child)->create();

        $this->artisan('genealogy:rebuild-edges', ['--fresh' => true])->assertSuccessful();

        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $father->id,
            'child_id' => $child->id,
            'edge_kind' => EdgeKind::Biological->value,
        ]);
        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $mother->id,
            'child_id' => $child->id,
        ]);
        $this->assertSame(2, DB::table('family_edges')->count());
    }

    public function test_an_adoptive_relationship_keeps_its_kind(): void
    {
        // The tree renders non-biological edges dashed, so the distinction has
        // to survive into the projection.
        $parent = Person::factory()->create();
        $child = Person::factory()->create();

        Relationship::factory()->parentChild($parent, $child)->adoptive()->create();

        $this->artisan('genealogy:rebuild-edges', ['--fresh' => true]);

        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'edge_kind' => EdgeKind::Adoptive->value,
        ]);
    }

    public function test_rebuilding_is_idempotent(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();
        Relationship::factory()->parentChild($parent, $child)->create();

        $this->artisan('genealogy:rebuild-edges', ['--fresh' => true]);
        $this->artisan('genealogy:rebuild-edges');
        $this->artisan('genealogy:rebuild-edges');

        $this->assertSame(1, DB::table('family_edges')->count());
    }

    public function test_a_deleted_relationship_drops_its_edge(): void
    {
        $parent = Person::factory()->create();
        $child = Person::factory()->create();
        $relationship = Relationship::factory()->parentChild($parent, $child)->create();

        $this->artisan('genealogy:rebuild-edges', ['--fresh' => true]);
        $this->assertSame(1, DB::table('family_edges')->count());

        $relationship->delete();
        $this->artisan('genealogy:rebuild-edges');

        $this->assertSame(0, DB::table('family_edges')->count());
    }

    public function test_descendant_traversal_returns_depth_ordered_generations(): void
    {
        $g0 = Person::factory()->bornExactly(1898)->create();
        $g1 = Person::factory()->bornExactly(1926)->create();
        $g2 = Person::factory()->bornExactly(1955)->create();
        $g3 = Person::factory()->bornExactly(1980)->create();

        Relationship::factory()->parentChild($g0, $g1)->create();
        Relationship::factory()->parentChild($g1, $g2)->create();
        Relationship::factory()->parentChild($g2, $g3)->create();

        $this->artisan('genealogy:rebuild-edges', ['--fresh' => true]);

        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE des (person_id, depth, path) AS (
                SELECT ?, 0, CAST(? AS CHAR(2000))
                UNION ALL
                SELECT fe.child_id, d.depth + 1, CONCAT(d.path, ',', fe.child_id)
                FROM des d
                JOIN family_edges fe ON fe.parent_id = d.person_id
                WHERE d.depth < ? AND FIND_IN_SET(fe.child_id, d.path) = 0
            )
            SELECT person_id, MIN(depth) AS depth
            FROM des GROUP BY person_id ORDER BY depth
        SQL, [$g0->id, $g0->id, 8]);

        $byPerson = collect($rows)->pluck('depth', 'person_id');

        $this->assertSame(0, (int) $byPerson[$g0->id]);
        $this->assertSame(1, (int) $byPerson[$g1->id]);
        $this->assertSame(2, (int) $byPerson[$g2->id]);
        $this->assertSame(3, (int) $byPerson[$g3->id]);
    }

    public function test_depth_limit_truncates_the_traversal(): void
    {
        // No endpoint may ever return an unbounded graph.
        $people = Person::factory()->count(5)->create();
        for ($i = 0; $i < 4; $i++) {
            Relationship::factory()->parentChild($people[$i], $people[$i + 1])->create();
        }

        $this->artisan('genealogy:rebuild-edges', ['--fresh' => true]);

        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE des (person_id, depth, path) AS (
                SELECT ?, 0, CAST(? AS CHAR(2000))
                UNION ALL
                SELECT fe.child_id, d.depth + 1, CONCAT(d.path, ',', fe.child_id)
                FROM des d
                JOIN family_edges fe ON fe.parent_id = d.person_id
                WHERE d.depth < ? AND FIND_IN_SET(fe.child_id, d.path) = 0
            )
            SELECT DISTINCT person_id FROM des
        SQL, [$people[0]->id, $people[0]->id, 2]);

        // Root plus two generations, and nothing beyond.
        $this->assertCount(3, $rows);
    }

    public function test_a_cycle_cannot_hang_the_traversal(): void
    {
        // Cycles are rejected at write time in Phase 6, but the path guard has
        // to hold even if a bad edge ever reaches the table.
        $a = Person::factory()->create();
        $b = Person::factory()->create();

        Relationship::factory()->parentChild($a, $b)->create();
        Relationship::factory()->parentChild($b, $a)->create();

        $this->artisan('genealogy:rebuild-edges', ['--fresh' => true]);

        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE des (person_id, depth, path) AS (
                SELECT ?, 0, CAST(? AS CHAR(2000))
                UNION ALL
                SELECT fe.child_id, d.depth + 1, CONCAT(d.path, ',', fe.child_id)
                FROM des d
                JOIN family_edges fe ON fe.parent_id = d.person_id
                WHERE d.depth < ? AND FIND_IN_SET(fe.child_id, d.path) = 0
            )
            SELECT DISTINCT person_id FROM des
        SQL, [$a->id, $a->id, 8]);

        $this->assertCount(2, $rows);
    }
}
