<?php

declare(strict_types=1);

namespace Tests\Feature\Tree;

use App\Enums\PrivacyLevel;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\UnionChild;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TreeTraversalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tribe $tribe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create(['is_super_admin' => true]);
        $this->tribe = Tribe::factory()->create();
    }

    private function person(int $birth, array $overrides = []): Person
    {
        return Person::factory()->bornExactly($birth)->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Public,
            ...$overrides,
        ]);
    }

    private function parent(Person $parent, Person $child): void
    {
        Relationship::factory()->parentChild($parent, $child)->create();
    }

    /** Five generations, one line. @return array<int, Person> */
    private function chain(int $length): array
    {
        $people = [];
        $year = 1860;

        for ($i = 0; $i < $length; $i++) {
            $people[] = $this->person($year);
            $year += 28;
        }

        for ($i = 0; $i + 1 < $length; $i++) {
            $this->parent($people[$i], $people[$i + 1]);
        }

        return $people;
    }

    public function test_the_tree_returns_ancestors_and_descendants_with_signed_depths(): void
    {
        $chain = $this->chain(5);
        $middle = $chain[2];

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $middle, 'ancestors' => 2, 'descendants' => 2]))
            ->assertOk();

        $depths = collect($response->json('data.people'))->pluck('depth', 'ulid');

        $this->assertSame(0, $depths[$middle->ulid]);
        $this->assertSame(-1, $depths[$chain[1]->ulid], 'A parent is one layer up');
        $this->assertSame(-2, $depths[$chain[0]->ulid]);
        $this->assertSame(1, $depths[$chain[3]->ulid], 'A child is one layer down');
        $this->assertSame(2, $depths[$chain[4]->ulid]);
    }

    public function test_depth_limits_are_honoured(): void
    {
        $chain = $this->chain(6);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $chain[5], 'ancestors' => 2, 'descendants' => 0]))
            ->assertOk();

        $ulids = collect($response->json('data.people'))->pluck('ulid');

        $this->assertCount(3, $ulids, 'Focus plus two generations, and nothing beyond');
        $this->assertNotContains($chain[2]->ulid, $ulids->all());
    }

    public function test_a_depth_beyond_the_cap_is_refused_rather_than_silently_clamped(): void
    {
        // A silent clamp leaves the client believing it received everything.
        $person = $this->person(1950);

        $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $person, 'ancestors' => 99]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['ancestors']]);
    }

    public function test_the_node_budget_truncates_the_furthest_generations_first(): void
    {
        $chain = $this->chain(6);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', [
                'person' => $chain[0], 'ancestors' => 0, 'descendants' => 5, 'budget' => 3,
            ]))
            ->assertOk();

        $this->assertTrue($response->json('meta.truncated'));
        $this->assertSame(3, $response->json('meta.node_count'));

        $ulids = collect($response->json('data.people'))->pluck('ulid')->all();
        $this->assertContains($chain[0]->ulid, $ulids, 'The focus is never dropped');
        $this->assertContains($chain[1]->ulid, $ulids, 'Nearer generations survive truncation');
        $this->assertNotContains($chain[5]->ulid, $ulids, 'The furthest generation is dropped first');
    }

    public function test_spouses_appear_at_the_same_layer_as_their_partner(): void
    {
        // A tree without spouses is not a family tree.
        $husband = $this->person(1920);
        $wife = $this->person(1924);
        Union::factory()->between($husband, $wife)->create();

        $child = $this->person(1950);
        $this->parent($husband, $child);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $child, 'ancestors' => 1, 'descendants' => 0]))
            ->assertOk();

        $depths = collect($response->json('data.people'))->pluck('depth', 'ulid');

        $this->assertSame(-1, $depths[$husband->ulid]);
        $this->assertSame(-1, $depths[$wife->ulid], 'A spouse sits beside their partner, not on another layer');
    }

    public function test_unions_carry_their_children_in_birth_order(): void
    {
        $a = $this->person(1900);
        $b = $this->person(1902);
        $union = Union::factory()->between($a, $b)->create();

        $children = [];
        foreach ([1926, 1929, 1931] as $i => $year) {
            $child = $this->person($year);
            UnionChild::create([
                'union_id' => $union->id, 'person_id' => $child->id, 'birth_order' => $i + 1,
            ]);
            $this->parent($a, $child);
            $children[] = $child;
        }

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $a, 'descendants' => 1]))
            ->assertOk();

        $this->assertSame(
            array_map(fn (Person $c) => $c->ulid, $children),
            $response->json('data.unions.0.children'),
        );
    }

    public function test_adoptive_edges_are_marked_for_dashed_rendering(): void
    {
        $parent = $this->person(1900);
        $child = $this->person(1930);
        Relationship::factory()->parentChild($parent, $child)->adoptive()->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $parent, 'descendants' => 1]))
            ->assertOk();

        $this->assertTrue($response->json('data.edges.0.dashed'));
        $this->assertSame('adoptive', $response->json('data.edges.0.kind'));
    }

    public function test_expandable_reports_only_what_is_not_already_shown(): void
    {
        // Otherwise every node looks expandable and the UI draws affordances
        // that do nothing when tapped.
        $parent = $this->person(1900);
        $children = collect(range(1, 5))->map(fn () => $this->person(1930));
        $children->each(fn (Person $c) => $this->parent($parent, $c));

        $shallow = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $parent, 'ancestors' => 0, 'descendants' => 0]))
            ->assertOk();

        $this->assertSame(5, $shallow->json("meta.expandable.{$parent->ulid}.children"));

        $full = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $parent, 'ancestors' => 0, 'descendants' => 1]))
            ->assertOk();

        $this->assertArrayNotHasKey($parent->ulid, $full->json('meta.expandable') ?? []);
    }

    public function test_a_cycle_in_the_data_cannot_hang_the_traversal(): void
    {
        // Cycles are rejected on write, but the path guard has to hold even if
        // a bad edge ever reaches the table.
        $a = $this->person(1900);
        $b = $this->person(1930);

        DB::table('family_edges')->insert([
            ['parent_id' => $a->id, 'child_id' => $b->id, 'edge_kind' => 1, 'tribe_id' => $this->tribe->id, 'confidence' => 50],
            ['parent_id' => $b->id, 'child_id' => $a->id, 'edge_kind' => 1, 'tribe_id' => $this->tribe->id, 'confidence' => 50],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $a, 'ancestors' => 8, 'descendants' => 8]))
            ->assertOk();

        $this->assertCount(2, $response->json('data.people'));
    }

    public function test_the_query_count_does_not_grow_with_the_size_of_the_tree(): void
    {
        // The whole traversal design rests on this. A lazily loaded relation is
        // the usual way a fixed budget quietly becomes unbounded.
        $small = $this->chain(3);
        $this->actingAs($this->user)->getJson(route('api.v1.tree.show', ['person' => $small[1]]));

        $large = $this->chain(6);
        foreach (range(1, 12) as $i) {
            $child = $this->person(1980);
            $this->parent($large[3], $child);
        }

        DB::enableQueryLog();
        $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.show', ['person' => $large[3], 'ancestors' => 3, 'descendants' => 3]))
            ->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            25,
            $queries,
            "The tree endpoint ran {$queries} queries; the budget is fixed regardless of tree size."
        );
    }
}
