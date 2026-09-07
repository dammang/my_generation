<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

use App\Actions\Genealogy\AddChildToUnion;
use App\Enums\ChildRelationshipType;
use App\Models\Person;
use App\Models\Union;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Correcting a family: who has died, what order the children came in, and
 * removing somebody entered by mistake.
 */
class FamilyEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['is_super_admin' => true]);
    }

    #[Test]
    public function a_death_can_be_recorded_without_a_date(): void
    {
        // Born recently enough that only the declaration can make them dead:
        // the factory picks a year back to 1900, and anybody past the maximum
        // age is counted as deceased whatever the flag says.
        $person = Person::factory()->bornExactly(1990)->create([
            'display_name' => 'Thawng Dam',
        ]);

        $this->assertTrue($person->is_living, 'no dates at all reads as living');

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), ['deceased_declared' => true])
            ->assertSuccessful();

        // is_living is derived and rewritten on every save, so the declaration
        // has to be a fact of its own — set the flag directly and the observer
        // would put it straight back.
        $person->refresh();

        $this->assertTrue($person->deceased_declared);
        $this->assertFalse($person->is_living);
        $this->assertNull($person->death_year, 'no date was invented');
    }

    #[Test]
    public function saying_they_are_living_again_undoes_it(): void
    {
        $person = Person::factory()->bornExactly(1990)->create([
            'deceased_declared' => true,
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), ['deceased_declared' => false])
            ->assertSuccessful();

        $this->assertTrue($person->refresh()->is_living);
    }

    #[Test]
    public function a_recorded_death_date_still_outranks_the_toggle(): void
    {
        $person = Person::factory()->bornExactly(1990)->create();

        // Through the API, because death_year is derived and a factory cannot
        // set it: the date is parsed from what somebody actually typed.
        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), ['death' => '1998'])
            ->assertSuccessful();

        $this->assertFalse($person->refresh()->is_living);

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), ['deceased_declared' => false])
            ->assertSuccessful();

        // Turning the toggle off is "we did not mean to say that", not "they
        // are alive" — a recorded date is evidence and the flag is not.
        $this->assertFalse($person->refresh()->is_living);
    }

    #[Test]
    public function a_relative_can_be_added_as_already_died(): void
    {
        $anchor = Person::factory()->bornExactly(1960)->create();

        $ulid = $this->actingAs($this->user)
            ->postJson(route('api.v1.people.relatives', $anchor), [
                'relation' => 'son',
                'person' => ['display_name' => 'Pu Zo', 'deceased_declared' => true],
            ])
            ->assertCreated()
            ->json('data.person.ulid');

        // Somebody added from memory is usually somebody who has died, and the
        // death field asks for a year nobody has.
        $person = Person::where('ulid', $ulid)->firstOrFail();

        $this->assertTrue($person->deceased_declared);
        $this->assertFalse($person->is_living);
        $this->assertNull($person->death_year);
    }

    #[Test]
    public function children_can_be_put_in_the_order_they_were_born(): void
    {
        [$union, $children] = $this->familyOfThree();

        [$first, $second, $third] = $children;

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.unions.children.order', $union), [
                'person_ulids' => [$third->ulid, $first->ulid, $second->ulid],
            ])
            ->assertOk()
            ->assertJsonPath('data.children.0.ulid', $third->ulid)
            ->assertJsonPath('data.children.0.birth_order', 1)
            ->assertJsonPath('data.children.2.ulid', $second->ulid);

        $this->assertSame(
            [1, 2, 3],
            DB::table('union_children')
                ->where('union_id', $union->id)
                ->orderBy('birth_order')
                ->pluck('birth_order')
                ->all(),
            'every child holds one place, and no two share it',
        );
    }

    #[Test]
    public function a_child_from_another_family_is_refused(): void
    {
        [$union] = $this->familyOfThree();
        $stranger = Person::factory()->create();

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.unions.children.order', $union), [
                'person_ulids' => [$stranger->ulid],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function somebody_entered_by_mistake_can_be_removed(): void
    {
        [, $children] = $this->familyOfThree();

        $this->actingAs($this->user)
            ->deleteJson(route('api.v1.people.destroy', $children[0]))
            ->assertNoContent();

        // Soft deleted: the record leaves the graph, the history does not.
        $this->assertSoftDeleted('people', ['id' => $children[0]->id]);
    }

    #[Test]
    public function a_child_can_be_moved_to_the_other_marriage(): void
    {
        [$father, $first, $second, $child] = $this->twoMarriages();

        $this->actingAs($this->user)
            ->postJson(route('api.v1.unions.children.move', [$first, $child]), [
                'union_ulid' => $second->ulid,
            ])
            ->assertOk()
            ->assertJsonPath('data.children.0.ulid', $child->ulid);

        $this->assertDatabaseMissing('union_children', [
            'union_id' => $first->id,
            'person_id' => $child->id,
        ]);

        // The edges belonging to the old marriage go with it, or the archive
        // would assert both mothers at once — which is the thing being
        // corrected.
        $mothers = DB::table('relationships')
            ->where('related_person_id', $child->id)
            ->whereNull('deleted_at')
            ->pluck('person_id');

        $this->assertContains($father->id, $mothers);
        $this->assertContains($second->partner_2_id, $mothers);
        $this->assertNotContains($first->partner_2_id, $mothers);
    }

    #[Test]
    public function moving_a_child_keeps_how_they_joined_the_family(): void
    {
        [, $first, $second, $child] = $this->twoMarriages(
            kind: ChildRelationshipType::Adoptive,
        );

        $this->actingAs($this->user)
            ->postJson(route('api.v1.unions.children.move', [$first, $child]), [
                'union_ulid' => $second->ulid,
            ])
            ->assertOk();

        // An adopted child moved between two of the same father's marriages is
        // still adopted, and re-deriving it would quietly lose it.
        $this->assertDatabaseHas('union_children', [
            'union_id' => $second->id,
            'person_id' => $child->id,
            'relationship_type' => ChildRelationshipType::Adoptive->value,
        ]);
    }

    #[Test]
    public function a_child_cannot_be_moved_to_a_couple_with_nobody_in_common(): void
    {
        [, $first, , $child] = $this->twoMarriages();
        $strangers = Union::factory()->create([
            'partner_1_id' => Person::factory()->bornExactly(1930)->create()->id,
            'partner_2_id' => Person::factory()->bornExactly(1935)->create()->id,
        ]);

        // Not a correction about which mother — a different claim about who
        // the child is, and it should be made by saying so.
        $this->actingAs($this->user)
            ->postJson(route('api.v1.unions.children.move', [$first, $child]), [
                'union_ulid' => $strangers->ulid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'UNIONS_UNRELATED');

        $this->assertDatabaseHas('union_children', [
            'union_id' => $first->id,
            'person_id' => $child->id,
        ]);
    }

    /**
     * One man, two wives, and a child recorded under the first.
     *
     * @return array{0: Person, 1: Union, 2: Union, 3: Person}
     */
    private function twoMarriages(
        ChildRelationshipType $kind = ChildRelationshipType::Biological,
    ): array {
        $father = Person::factory()->bornExactly(1940)->create();

        $first = Union::factory()->create([
            'partner_1_id' => $father->id,
            'partner_2_id' => Person::factory()->bornExactly(1945)->create()->id,
        ]);

        $second = Union::factory()->create([
            'partner_1_id' => $father->id,
            'partner_2_id' => Person::factory()->bornExactly(1950)->create()->id,
            'order_index' => 2,
        ]);

        $child = Person::factory()->bornExactly(1970)->create();

        app(AddChildToUnion::class)->handle($first, $child, $kind, 1);

        return [$father, $first, $second, $child];
    }

    /** @return array{0: Union, 1: array<int, Person>} */
    private function familyOfThree(): array
    {
        $father = Person::factory()->create();
        $union = Union::factory()->create(['partner_1_id' => $father->id]);

        $children = [];

        foreach (['Zen', 'Kam', 'Thawng'] as $i => $name) {
            $child = Person::factory()->create(['display_name' => $name]);

            DB::table('union_children')->insert([
                'union_id' => $union->id,
                'person_id' => $child->id,
                'birth_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $children[] = $child;
        }

        return [$union, $children];
    }
}
