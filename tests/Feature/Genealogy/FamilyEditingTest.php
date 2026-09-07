<?php

declare(strict_types=1);

namespace Tests\Feature\Genealogy;

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
        $person = Person::factory()->create(['display_name' => 'Thawng Dam']);

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
        $person = Person::factory()->create(['deceased_declared' => true]);

        $this->actingAs($this->user)
            ->patchJson(route('api.v1.people.update', $person), ['deceased_declared' => false])
            ->assertSuccessful();

        $this->assertTrue($person->refresh()->is_living);
    }

    #[Test]
    public function a_recorded_death_date_still_outranks_the_toggle(): void
    {
        $person = Person::factory()->create();

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
