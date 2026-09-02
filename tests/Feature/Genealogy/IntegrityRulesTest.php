<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Actions\Genealogy\AssertNoCycle;
use App\Exceptions\CycleDetectedException;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two rules, deliberately different in kind.
 *
 * Cycles are a hard error: they make every traversal below them incorrect.
 * Everything else is a warning, because historical records are wrong and
 * blocking a contributor loses the data forever.
 */
class IntegrityRulesTest extends TestCase
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

    private function person(array $overrides = []): Person
    {
        return Person::factory()->create(['tribe_id' => $this->tribe->id, ...$overrides]);
    }

    private function link(Person $parent, Person $child)
    {
        return $this->actingAs($this->user)->postJson(route('api.v1.relationships.store'), [
            'person_ulid' => $parent->ulid,
            'related_person_ulid' => $child->ulid,
        ]);
    }

    // ── Cycles: hard errors ──────────────────────────────────────────────

    public function test_a_person_cannot_be_their_own_parent(): void
    {
        $person = $this->person();

        $this->actingAs($this->user)->postJson(route('api.v1.relationships.store'), [
            'person_ulid' => $person->ulid,
            'related_person_ulid' => $person->ulid,
        ])->assertStatus(422);
    }

    public function test_a_direct_loop_is_rejected(): void
    {
        $a = $this->person();
        $b = $this->person();

        $this->link($a, $b)->assertCreated();

        $this->link($b, $a)
            ->assertStatus(422)
            ->assertJsonPath('code', 'RELATIONSHIP_CYCLE');
    }

    public function test_an_indirect_loop_three_generations_deep_is_rejected(): void
    {
        $a = $this->person();
        $b = $this->person();
        $c = $this->person();

        $this->link($a, $b)->assertCreated();
        $this->link($b, $c)->assertCreated();

        $this->link($c, $a)
            ->assertStatus(422)
            ->assertJsonPath('code', 'RELATIONSHIP_CYCLE');
    }

    public function test_the_cycle_message_names_the_people_involved(): void
    {
        // Telling a contributor only that "a loop exists" leaves them to find
        // which of a hundred edges caused it.
        $a = $this->person(['first_name' => 'Kin', 'last_name' => 'Tun']);
        $b = $this->person(['first_name' => 'Pau', 'last_name' => 'Zam']);

        $this->link($a, $b)->assertCreated();

        $message = $this->link($b, $a)->json('message');

        $this->assertStringContainsString('Kin Tun', $message);
        $this->assertStringContainsString('Pau Zam', $message);
    }

    public function test_a_rejected_cycle_writes_nothing(): void
    {
        $a = $this->person();
        $b = $this->person();
        $this->link($a, $b)->assertCreated();

        $this->link($b, $a)->assertStatus(422);

        $this->assertSame(1, Relationship::count());
        $this->assertSame(1, DB::table('family_edges')->count());
    }

    public function test_a_diamond_is_not_a_cycle(): void
    {
        // Pedigree collapse: cousins marrying is common in small clans and must
        // not be mistaken for a loop.
        $founder = $this->person();
        $left = $this->person();
        $right = $this->person();
        $descendant = $this->person();

        $this->link($founder, $left)->assertCreated();
        $this->link($founder, $right)->assertCreated();
        $this->link($left, $descendant)->assertCreated();
        $this->link($right, $descendant)->assertCreated();

        $this->assertSame(4, Relationship::count());
    }

    public function test_the_cycle_check_is_reusable_outside_http(): void
    {
        $a = $this->person();
        $b = $this->person();
        $this->link($a, $b)->assertCreated();

        $this->expectException(CycleDetectedException::class);

        app(AssertNoCycle::class)->handle($b->id, $a->id);
    }

    // ── Warnings: never block ────────────────────────────────────────────

    public function test_a_child_born_before_their_parent_is_flagged_not_refused(): void
    {
        $parent = $this->person(['first_name' => 'Kin', 'last_name' => 'Tun']);
        $parent->setUncertainDate('birth', '1950')->save();

        $child = $this->person();
        $child->setUncertainDate('birth', '1930')->save();

        $response = $this->link($parent, $child)->assertCreated();

        $codes = collect($response->json('warnings'))->pluck('code');

        $this->assertContains('CHILD_BORN_BEFORE_PARENT', $codes->all());
        $this->assertDatabaseHas('relationships', [
            'person_id' => $parent->id,
            'related_person_id' => $child->id,
        ]);
    }

    public function test_an_implausibly_young_parent_is_flagged(): void
    {
        $parent = $this->person();
        $parent->setUncertainDate('birth', '1950')->save();
        $child = $this->person();
        $child->setUncertainDate('birth', '1958')->save();

        $codes = collect($this->link($parent, $child)->assertCreated()->json('warnings'))->pluck('code');

        $this->assertContains('PARENT_AGE_LOW', $codes->all());
    }

    public function test_a_posthumous_birth_within_a_year_is_not_flagged(): void
    {
        // A child born months after the father's death is ordinary, and
        // flagging it would train contributors to ignore warnings.
        $father = $this->person(['gender' => 'male']);
        $father->setUncertainDate('birth', '1900')->setUncertainDate('death', '1940')->save();

        $child = $this->person();
        $child->setUncertainDate('birth', '1940')->save();

        $codes = collect($this->link($father, $child)->assertCreated()->json('warnings'))->pluck('code');

        $this->assertNotContains('CHILD_BORN_AFTER_PARENT_DEATH', $codes->all());
    }

    public function test_a_birth_long_after_the_parents_death_is_flagged(): void
    {
        $father = $this->person(['gender' => 'male']);
        $father->setUncertainDate('birth', '1900')->setUncertainDate('death', '1940')->save();

        $child = $this->person();
        $child->setUncertainDate('birth', '1950')->save();

        $codes = collect($this->link($father, $child)->assertCreated()->json('warnings'))->pluck('code');

        $this->assertContains('CHILD_BORN_AFTER_PARENT_DEATH', $codes->all());
    }

    public function test_a_death_before_a_birth_is_flagged_on_the_person(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('api.v1.people.store'), [
            'first_name' => 'Odd',
            'last_name' => 'Record',
            'birth' => '1950',
            'death' => '1930',
        ])->assertCreated();

        $codes = collect($response->json('warnings'))->pluck('code');

        $this->assertContains('DEATH_BEFORE_BIRTH', $codes->all());
    }

    public function test_an_implausible_lifespan_is_flagged(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('api.v1.people.store'), [
            'first_name' => 'Very',
            'last_name' => 'Old',
            'birth' => '1700',
            'death' => '1900',
        ])->assertCreated();

        $this->assertContains(
            'IMPLAUSIBLE_LIFESPAN',
            collect($response->json('warnings'))->pluck('code')->all()
        );
    }

    public function test_a_marriage_before_the_minimum_age_is_flagged(): void
    {
        $a = $this->person();
        $a->setUncertainDate('birth', '1950')->save();
        $b = $this->person();
        $b->setUncertainDate('birth', '1952')->save();

        $response = $this->actingAs($this->user)->postJson(route('api.v1.unions.store'), [
            'partner_1_ulid' => $a->ulid,
            'partner_2_ulid' => $b->ulid,
            'marriage' => '1958',
        ])->assertCreated();

        $this->assertContains(
            'MARRIAGE_AGE_LOW',
            collect($response->json('warnings'))->pluck('code')->all()
        );
    }

    public function test_an_ordinary_record_carries_no_warnings(): void
    {
        // If routine data produced warnings, contributors would learn to ignore
        // them and the mechanism would be worthless.
        $parent = $this->person();
        $parent->setUncertainDate('birth', '1900')->save();
        $child = $this->person();
        $child->setUncertainDate('birth', '1930')->save();

        $this->assertSame([], $this->link($parent, $child)->assertCreated()->json('warnings'));
    }
}
