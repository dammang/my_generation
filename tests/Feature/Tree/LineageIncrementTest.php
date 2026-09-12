<?php

declare(strict_types=1);

namespace Tests\Feature\Tree;

use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Tribe;
use App\Services\Tree\LineageDepthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Adding somebody without recomputing the world.
 *
 * Every counted root used to be rebuilt on every new parent-child edge: 3,531
 * rows and 320ms of a 350ms request on the real archive, growing with it. An
 * addition implies almost nothing — the newcomer stands one generation below
 * their parent on every scale the parent stands on.
 *
 * The only thing that matters is that the cheap answer is the same answer, so
 * that is what these assert: the table after an add is identical to the table
 * a full rebuild would have produced.
 */
class LineageIncrementTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
    }

    public function test_an_added_child_lands_where_a_full_rebuild_would_put_them(): void
    {
        $founder = $this->person();
        $son = $this->person();
        $grandson = $this->person();

        $this->parent($founder, $son);
        $this->parent($son, $grandson);

        $this->countFrom($founder);

        // The addition under test, taking the fast path through the observer.
        $greatGrandson = $this->person();
        $this->parent($grandson, $greatGrandson);

        // Snapshotted before the rebuild, because the rebuild rewrites the
        // table: comparing after it would compare the rebuild to itself and
        // pass however wrong the cheap path was.
        $fast = $this->stored();

        $this->assertSame($this->rebuilt(), $fast);
    }

    public function test_a_whole_branch_attached_at_once_lands_correctly_too(): void
    {
        // Rarer than a new person, and the case where the cheap arithmetic
        // could quietly be wrong: everybody beneath the newcomer moves with
        // them.
        $founder = $this->person();
        $son = $this->person();

        $this->parent($founder, $son);
        $this->countFrom($founder);

        // A family built away from the counted line, then joined to it.
        $outsider = $this->person();
        $child = $this->person();
        $grandchild = $this->person();

        $this->parent($outsider, $child);
        $this->parent($child, $grandchild);

        $this->parent($son, $outsider);

        // Snapshotted before the rebuild, because the rebuild rewrites the
        // table: comparing after it would compare the rebuild to itself and
        // pass however wrong the cheap path was.
        $fast = $this->stored();

        $this->assertSame($this->rebuilt(), $fast);
    }

    public function test_a_second_line_to_the_same_person_keeps_both_distances(): void
    {
        // Pedigree collapse: cousins marry, and a person is genuinely nearer
        // down one line than the other. The shorter must win the label and the
        // longer must survive as the maximum.
        $founder = $this->person();
        $left = $this->person();
        $right = $this->person();
        $deep = $this->person();

        $this->parent($founder, $left);
        $this->parent($founder, $right);
        $this->parent($right, $deep);

        $this->countFrom($founder);

        $shared = $this->person();
        $this->parent($left, $shared);
        $this->parent($deep, $shared);

        // Snapshotted before the rebuild, because the rebuild rewrites the
        // table: comparing after it would compare the rebuild to itself and
        // pass however wrong the cheap path was.
        $fast = $this->stored();

        $this->assertSame($this->rebuilt(), $fast);
    }

    public function test_somebody_under_no_counted_root_costs_nothing(): void
    {
        $this->countFrom($this->person());

        $stranger = $this->person();
        $child = $this->person();

        $this->parent($stranger, $child);

        $this->assertSame(
            0,
            DB::table('lineage_depths')->where('person_id', $child->id)->count(),
            'a person outside every counted line was given a depth anyway',
        );
    }

    /** Every stored depth, in a comparable shape. */
    private function stored(): array
    {
        return DB::table('lineage_depths')
            ->orderBy('root_person_id')
            ->orderBy('person_id')
            ->get(['root_person_id', 'person_id', 'depth', 'min_depth', 'max_depth', 'path_count'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** What a full recompute of every root would have written instead. */
    private function rebuilt(): array
    {
        app(LineageDepthService::class)->refreshKnownRoots();

        return $this->stored();
    }

    private function countFrom(Person $ancestor): void
    {
        $this->clan->forceFill([
            'ancestor_person_id' => $ancestor->id,
            'counting_origin_person_id' => $ancestor->id,
        ])->save();

        app(LineageDepthService::class)->recomputeFor($ancestor);
    }

    private function parent(Person $parent, Person $child): void
    {
        Relationship::factory()->parentChild($parent, $child)->create();
    }

    private function person(): Person
    {
        return Person::factory()->bornExactly(1990)->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'privacy_level' => PrivacyLevel::Clan,
        ]);
    }
}
