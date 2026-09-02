<?php

declare(strict_types=1);

namespace Tests\Feature\Tree;

use App\Enums\PrivacyLevel;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Tree\LineageDepthService;
use App\Services\Tree\RelationshipPathFinder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineageAndPathTest extends TestCase
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

    private function person(int $birth = 1950, array $overrides = []): Person
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

    // ── Lineage ──────────────────────────────────────────────────────────

    public function test_lineage_returns_the_direct_line_to_the_founder(): void
    {
        $founder = $this->person(1860);
        $gen1 = $this->person(1890);
        $gen2 = $this->person(1920);
        $gen3 = $this->person(1950);

        $this->parent($founder, $gen1);
        $this->parent($gen1, $gen2);
        $this->parent($gen2, $gen3);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.lineage', $gen3))
            ->assertOk();

        $names = collect($response->json('data.line'))->pluck('ulid')->all();

        $this->assertSame(
            [$gen3->ulid, $gen2->ulid, $gen1->ulid, $founder->ulid],
            $names,
            'The line runs from the person upward to the founder'
        );
    }

    public function test_generation_depth_is_reported_from_the_branch_ancestor(): void
    {
        $founder = $this->person(1860);
        $branch = FamilyBranch::factory()->create([
            'tribe_id' => $this->tribe->id,
            'ancestor_person_id' => $founder->id,
        ]);

        $child = $this->person(1890, ['family_branch_id' => $branch->id]);
        $grandchild = $this->person(1920, ['family_branch_id' => $branch->id]);
        $this->parent($founder, $child);
        $this->parent($child, $grandchild);

        $this->artisan('genealogy:recompute-lineage')->assertSuccessful();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.lineage', $grandchild))
            ->assertOk();

        $this->assertSame(2, $response->json('data.generation.depth'));
        $this->assertSame('Generation 2', $response->json('meta.generation_display'));
        $this->assertFalse($response->json('data.generation.collapsed'));
    }

    public function test_pedigree_collapse_reports_a_range_not_a_single_number(): void
    {
        // Cousins marrying is common in small clans. A person can genuinely sit
        // at two depths from the same founder, and inventing one answer is a lie.
        $founder = $this->person(1800);
        $branch = FamilyBranch::factory()->create([
            'tribe_id' => $this->tribe->id,
            'ancestor_person_id' => $founder->id,
        ]);

        $shortLine = $this->person(1830, ['family_branch_id' => $branch->id]);
        $longA = $this->person(1830, ['family_branch_id' => $branch->id]);
        $longB = $this->person(1860, ['family_branch_id' => $branch->id]);
        $descendant = $this->person(1890, ['family_branch_id' => $branch->id]);

        $this->parent($founder, $shortLine);
        $this->parent($founder, $longA);
        $this->parent($longA, $longB);

        // Two lines of different length reach the same descendant.
        $this->parent($shortLine, $descendant);
        $this->parent($longB, $descendant);

        $this->artisan('genealogy:recompute-lineage')->assertSuccessful();

        $depth = app(LineageDepthService::class)->forPerson($descendant->fresh());

        $this->assertSame(2, $depth['min_depth']);
        $this->assertSame(3, $depth['max_depth']);
        $this->assertTrue($depth['collapsed']);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.lineage', $descendant))
            ->assertOk();

        $this->assertSame('Generation 2–3', $response->json('meta.generation_display'));
    }

    // ── Relationship path ────────────────────────────────────────────────

    /**
     * @return array{
     *     grandparent: Person, childA: Person, childB: Person,
     *     cousinA: Person, cousinB: Person
     * }
     */
    private function cousinFamily(): array
    {
        $grandparent = $this->person(1900);
        $childA = $this->person(1930);
        $childB = $this->person(1932);
        $cousinA = $this->person(1960);
        $cousinB = $this->person(1962);

        $this->parent($grandparent, $childA);
        $this->parent($grandparent, $childB);
        $this->parent($childA, $cousinA);
        $this->parent($childB, $cousinB);

        return compact('grandparent', 'childA', 'childB', 'cousinA', 'cousinB');
    }

    public function test_it_names_a_parent(): void
    {
        $parent = $this->person(1930);
        $child = $this->person(1960);
        $this->parent($parent, $child);

        $result = app(RelationshipPathFinder::class)->between($child, $parent);

        $this->assertSame('parent', $result['label']);
    }

    public function test_it_names_a_grandchild(): void
    {
        $grandparent = $this->person(1900);
        $middle = $this->person(1930);
        $grandchild = $this->person(1960);
        $this->parent($grandparent, $middle);
        $this->parent($middle, $grandchild);

        $this->assertSame(
            'grandchild',
            app(RelationshipPathFinder::class)->between($grandparent, $grandchild)['label']
        );
    }

    public function test_it_names_siblings(): void
    {
        $parent = $this->person(1930);
        $a = $this->person(1960);
        $b = $this->person(1962);
        $this->parent($parent, $a);
        $this->parent($parent, $b);

        $this->assertSame('sibling', app(RelationshipPathFinder::class)->between($a, $b)['label']);
    }

    public function test_it_names_first_cousins(): void
    {
        ['grandparent' => $grandparent, 'cousinA' => $cousinA, 'cousinB' => $cousinB] = $this->cousinFamily();

        $result = app(RelationshipPathFinder::class)->between($cousinA, $cousinB);

        $this->assertSame('first cousin', $result['label']);
        $this->assertSame($grandparent->id, $result['common_ancestor']->id);
    }

    public function test_it_names_an_aunt_or_uncle(): void
    {
        // childB is the uncle: cousinA's parent's sibling, not cousinA's parent.
        ['cousinA' => $cousinA, 'childB' => $uncle] = $this->cousinFamily();

        $this->assertSame(
            'aunt or uncle',
            app(RelationshipPathFinder::class)->between($cousinA, $uncle)['label']
        );

        $this->assertSame(
            'niece or nephew',
            app(RelationshipPathFinder::class)->between($uncle, $cousinA)['label']
        );
    }

    public function test_unrelated_people_are_reported_as_unrelated(): void
    {
        $a = $this->person(1950);
        $b = $this->person(1950);

        $result = app(RelationshipPathFinder::class)->between($a, $b);

        $this->assertFalse($result['related']);
        $this->assertNull($result['label']);
    }

    public function test_the_path_endpoint_answers_over_http(): void
    {
        ['cousinA' => $cousinA, 'cousinB' => $cousinB] = $this->cousinFamily();

        $this->actingAs($this->user)
            ->getJson(route('api.v1.tree.path', ['person' => $cousinA, 'other' => $cousinB]))
            ->assertOk()
            ->assertJsonPath('data.related', true)
            ->assertJsonPath('data.label', 'first cousin')
            ->assertJsonPath('data.generations_up', 2)
            ->assertJsonPath('data.generations_down', 2);
    }
}
