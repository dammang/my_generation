<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Enums\PrivacyLevel;
use App\Models\Clan;
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

/**
 * The contributor picks a relationship label. They never learn that a union row
 * exists — these tests pin what actually gets written underneath.
 */
class AddRelativeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tribe $tribe;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['is_super_admin' => true]);
        $this->tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
    }

    private function anchor(array $overrides = []): Person
    {
        return Person::factory()->bornExactly(1955)->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'privacy_level' => PrivacyLevel::Tribe,
            ...$overrides,
        ]);
    }

    private function add(Person $anchor, string $relation, array $person = [], array $extra = [])
    {
        return $this->actingAs($this->user)->postJson(
            route('api.v1.people.relatives', $anchor),
            ['relation' => $relation, 'person' => $person ?: ['first_name' => 'New', 'last_name' => 'Relative'], ...$extra],
        );
    }

    public function test_adding_a_father_creates_a_person_and_a_parent_edge(): void
    {
        $anchor = $this->anchor();

        $response = $this->add($anchor, 'father', ['first_name' => 'Kin', 'last_name' => 'Tun', 'birth' => '1898'])
            ->assertCreated();

        $father = Person::where('display_name', 'Kin Tun')->firstOrFail();

        $this->assertDatabaseHas('relationships', [
            'person_id' => $father->id,
            'related_person_id' => $anchor->id,
            'relationship_type' => 'parent_child',
        ]);
        $this->assertSame(1, $response->json('data.created.people'));
    }

    public function test_adding_a_mother_after_a_father_produces_one_couple_not_two_families(): void
    {
        // The whole point of joining an existing union: otherwise the chart
        // shows two single-parent families for one child.
        $anchor = $this->anchor();

        $this->add($anchor, 'father', ['first_name' => 'Kin', 'last_name' => 'Tun'])->assertCreated();
        $this->add($anchor, 'mother', ['first_name' => 'Za', 'last_name' => 'Vung'])->assertCreated();

        $this->assertSame(1, Union::count());

        $union = Union::firstOrFail();
        $this->assertNotNull($union->partner_1_id);
        $this->assertNotNull($union->partner_2_id);
        $this->assertSame(1, UnionChild::where('person_id', $anchor->id)->count());
    }

    public function test_the_new_relative_inherits_the_anchors_placement(): void
    {
        // Making a contributor restate the clan every time is how placement
        // data goes missing.
        $anchor = $this->anchor();

        $this->add($anchor, 'father', ['first_name' => 'Kin', 'last_name' => 'Tun'])->assertCreated();

        $father = Person::where('display_name', 'Kin Tun')->firstOrFail();

        $this->assertSame($this->tribe->id, $father->tribe_id);
        $this->assertSame($this->clan->id, $father->clan_id);
    }

    public function test_adding_a_spouse_creates_a_normalised_union(): void
    {
        $anchor = $this->anchor();

        $this->add($anchor, 'spouse', ['first_name' => 'Khoi', 'last_name' => 'Dim'])->assertCreated();

        $union = Union::firstOrFail();
        $spouse = Person::where('display_name', 'Khoi Dim')->firstOrFail();

        $this->assertSame(min($anchor->id, $spouse->id), $union->partner_1_id);
        $this->assertSame(max($anchor->id, $spouse->id), $union->partner_2_id);
    }

    public function test_adding_a_son_writes_edges_for_both_partners(): void
    {
        $anchor = $this->anchor();
        $this->add($anchor, 'spouse', ['first_name' => 'Khoi', 'last_name' => 'Dim'])->assertCreated();

        $response = $this->add($anchor, 'son', ['first_name' => 'Thawng', 'last_name' => 'Dam', 'birth' => '1980'])
            ->assertCreated();

        $son = Person::where('display_name', 'Thawng Dam')->firstOrFail();

        $this->assertSame(2, Relationship::where('related_person_id', $son->id)->count());
        $this->assertSame(2, $response->json('data.created.relationships'));
        $this->assertSame(1, UnionChild::where('person_id', $son->id)->count());
    }

    public function test_adding_a_child_to_a_person_with_no_union_creates_a_single_parent_family(): void
    {
        // Single-parent families are real and common in historical records;
        // refusing them loses the child's whole line.
        $anchor = $this->anchor();

        $this->add($anchor, 'daughter', ['first_name' => 'Neng', 'last_name' => 'Kham'])->assertCreated();

        $union = Union::firstOrFail();

        $this->assertNull($union->partner_2_id);
        $this->assertSame(1, Relationship::count());
    }

    public function test_adding_a_child_to_someone_with_two_marriages_asks_which(): void
    {
        // Guessing would attach the child to the wrong marriage, and that error
        // is invisible until somebody notices the chart is wrong generations later.
        $anchor = $this->anchor();
        $this->add($anchor, 'spouse', ['first_name' => 'First', 'last_name' => 'Wife'])->assertCreated();
        $this->add($anchor, 'spouse', ['first_name' => 'Second', 'last_name' => 'Wife'])->assertCreated();

        $response = $this->add($anchor, 'son', ['first_name' => 'Some', 'last_name' => 'Child'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'UNION_AMBIGUOUS');

        $this->assertCount(2, $response->json('errors.union_ulid'));
    }

    public function test_naming_the_union_resolves_the_ambiguity(): void
    {
        $anchor = $this->anchor();
        $this->add($anchor, 'spouse', ['first_name' => 'First', 'last_name' => 'Wife'])->assertCreated();
        $this->add($anchor, 'spouse', ['first_name' => 'Second', 'last_name' => 'Wife'])->assertCreated();

        $second = Union::orderByDesc('order_index')->firstOrFail();

        $this->add($anchor, 'son', ['first_name' => 'Some', 'last_name' => 'Child'], ['union_ulid' => $second->ulid])
            ->assertCreated();

        $child = Person::where('display_name', 'Some Child')->firstOrFail();

        $this->assertSame($second->id, UnionChild::where('person_id', $child->id)->value('union_id'));
    }

    public function test_adding_a_brother_attaches_to_the_same_parents_and_writes_no_sibling_row(): void
    {
        // Siblings are derived. Storing sibship is O(n^2) per family and drifts.
        $anchor = $this->anchor();
        $this->add($anchor, 'father', ['first_name' => 'Kin', 'last_name' => 'Tun'])->assertCreated();
        $this->add($anchor, 'mother', ['first_name' => 'Za', 'last_name' => 'Vung'])->assertCreated();

        $this->add($anchor, 'brother', ['first_name' => 'Khai', 'last_name' => 'Vum'])->assertCreated();

        $brother = Person::where('display_name', 'Khai Vum')->firstOrFail();

        $this->assertSame(2, Relationship::where('related_person_id', $brother->id)->count());
        $this->assertSame(0, Relationship::where('relationship_type', 'sibling_asserted')->count());
        $this->assertCount(1, $anchor->siblings());
    }

    public function test_a_sibling_with_unknown_parents_becomes_an_asserted_relationship(): void
    {
        // "These two are brothers, we do not know their parents" — real, and
        // the only case that justifies storing a sibling row at all.
        $anchor = $this->anchor();

        $this->add($anchor, 'brother', ['first_name' => 'Khai', 'last_name' => 'Vum'])->assertCreated();

        $this->assertSame(1, Relationship::where('relationship_type', 'sibling_asserted')->count());
    }

    public function test_an_adoptive_child_keeps_its_subtype(): void
    {
        $anchor = $this->anchor();

        $this->add($anchor, 'son', ['first_name' => 'Tun', 'last_name' => 'Khoi'], [
            'relationship_subtype' => 'adoptive',
        ])->assertCreated();

        $this->assertDatabaseHas('relationships', [
            'relationship_subtype' => 'adoptive',
            'is_biological' => 0,
        ]);
        $this->assertDatabaseHas('union_children', ['relationship_type' => 'adoptive']);
    }

    public function test_the_whole_flow_is_one_transaction(): void
    {
        // A half-written family is worse than a failed request: nothing in the
        // UI would reveal a person whose edges never landed.
        $anchor = $this->anchor();
        $before = Person::count();

        // A cycle is impossible on a brand-new person, so force the failure by
        // pointing at a union the anchor does not own.
        $this->add($anchor, 'son', ['first_name' => 'Some', 'last_name' => 'Child'], [
            'union_ulid' => '01NOTAREALULIDNOTAREALULID',
        ])->assertStatus(422);

        $this->assertSame($before, Person::count());
    }

    public function test_a_relative_needs_at_least_one_name(): void
    {
        $this->add($this->anchor(), 'father', ['gender' => 'male'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['person.display_name']]);
    }

    public function test_adding_a_relative_counts_towards_contributions(): void
    {
        $anchor = $this->anchor();
        $this->add($anchor, 'father', ['first_name' => 'Kin', 'last_name' => 'Tun'])->assertCreated();

        $this->assertSame(
            1,
            (int) DB::table('contribution_stats')->where('user_id', $this->user->id)->value('people_added')
        );
    }

    public function test_the_new_relative_appears_in_the_graph_immediately(): void
    {
        $anchor = $this->anchor();
        $this->add($anchor, 'father', ['first_name' => 'Kin', 'last_name' => 'Tun'])->assertCreated();

        $father = Person::where('display_name', 'Kin Tun')->firstOrFail();

        // No rebuild step: the projection is synchronous, so the contributor
        // sees the father in the tree on the very next request.
        $this->assertDatabaseHas('family_edges', [
            'parent_id' => $father->id,
            'child_id' => $anchor->id,
        ]);
    }
}
