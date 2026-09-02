<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\Place;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\UnionChild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DerivedAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_name_is_composed_when_absent(): void
    {
        $person = Person::factory()->create([
            'display_name' => '',
            'first_name' => 'Thawng',
            'last_name' => 'Dam',
        ]);

        $this->assertSame('Thawng Dam', $person->display_name);
    }

    public function test_sort_name_folds_diacritics_so_ordering_is_stable(): void
    {
        $person = Person::factory()->create(['first_name' => 'Ṭhawng', 'last_name' => 'Dam']);

        $this->assertSame('dam thawng', $person->sort_name);
    }

    public function test_is_living_is_derived_from_the_death_record(): void
    {
        $person = Person::factory()->bornExactly(1926)->create();
        $this->assertTrue($person->is_living);

        $person->setUncertainDate('death', '1994');
        $person->save();

        $this->assertFalse($person->fresh()->is_living);
    }

    public function test_a_person_born_beyond_the_maximum_age_is_not_marked_living(): void
    {
        $person = Person::factory()->bornExactly(1850)->create();

        $this->assertFalse($person->is_living);
    }

    public function test_adding_a_person_increments_the_scope_counters(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);

        Person::factory()->count(3)->create(['tribe_id' => $tribe->id, 'clan_id' => $clan->id]);

        $this->assertSame(3, $tribe->fresh()->people_count);
        $this->assertSame(3, $clan->fresh()->people_count);
    }

    public function test_moving_a_person_between_tribes_moves_the_count(): void
    {
        $from = Tribe::factory()->create();
        $to = Tribe::factory()->create();
        $person = Person::factory()->create(['tribe_id' => $from->id]);

        $person->update(['tribe_id' => $to->id]);

        $this->assertSame(0, $from->fresh()->people_count);
        $this->assertSame(1, $to->fresh()->people_count);
    }

    public function test_a_counter_never_goes_negative(): void
    {
        $tribe = Tribe::factory()->create();
        $person = Person::factory()->create(['tribe_id' => $tribe->id]);

        $person->delete();
        $person->forceDelete();

        $this->assertSame(0, $tribe->fresh()->people_count);
    }

    public function test_every_scoped_entity_gets_a_scope_row_with_a_path(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $branch = FamilyBranch::factory()->create(['tribe_id' => $tribe->id, 'clan_id' => $clan->id]);

        $tribeScope = Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $tribe->id)->firstOrFail();
        $clanScope = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $clan->id)->firstOrFail();
        $branchScope = Scope::where('scopeable_type', 'family_branch')->where('scopeable_id', $branch->id)->firstOrFail();

        // Authority flows downward by prefix match, so a Tribe Admin needs no
        // row per clan.
        $this->assertStringStartsWith($tribeScope->path, $clanScope->path);
        $this->assertStringStartsWith($clanScope->path, $branchScope->path);
        $this->assertSame(0, $tribeScope->depth);
        $this->assertSame(1, $clanScope->depth);
        $this->assertSame(2, $branchScope->depth);
    }

    public function test_a_sub_clan_nests_under_its_parents_path(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $sub = Clan::factory()->under($clan)->create();

        $this->assertStringStartsWith($clan->fresh()->path, $sub->fresh()->path);
        $this->assertSame(1, $sub->fresh()->depth);
    }

    public function test_places_build_a_materialised_path(): void
    {
        $country = Place::factory()->ofType('country')->create(['name' => 'Myanmar']);
        $state = Place::factory()->ofType('state')->create(['name' => 'Chin State', 'parent_id' => $country->id]);
        $village = Place::factory()->create(['name' => 'Khuasak', 'parent_id' => $state->id]);

        $this->assertSame("/{$country->id}/", $country->fresh()->path);
        $this->assertSame("/{$country->id}/{$state->id}/{$village->id}/", $village->fresh()->path);
        $this->assertSame(2, $village->fresh()->depth);
        $this->assertSame('Khuasak, Chin State, Myanmar', $village->fresh()->fullName());
    }

    public function test_a_union_pair_is_normalised_regardless_of_argument_order(): void
    {
        [$a, $b] = Person::factory()->count(2)->create()->all();

        // Passed "backwards" — the observer normalises so the CHECK constraint
        // and the unique key both hold.
        $union = Union::create(['partner_1_id' => $b->id, 'partner_2_id' => $a->id]);

        $this->assertSame(min($a->id, $b->id), $union->partner_1_id);
        $this->assertSame(max($a->id, $b->id), $union->partner_2_id);
    }

    public function test_a_second_marriage_is_ordered_after_the_first(): void
    {
        [$person, $first, $second] = Person::factory()->count(3)->create()->all();

        $one = Union::factory()->between($person, $first)->create();
        $two = Union::factory()->between($person, $second)->create();

        $this->assertSame(1, $one->order_index);
        $this->assertSame(2, $two->order_index);
    }

    public function test_children_count_tracks_the_grouping_rows(): void
    {
        [$a, $b] = Person::factory()->count(2)->create()->all();
        $union = Union::factory()->between($a, $b)->create();
        $children = Person::factory()->count(3)->create();

        foreach ($children as $child) {
            UnionChild::create(['union_id' => $union->id, 'person_id' => $child->id]);
        }

        $this->assertSame(3, $union->fresh()->children_count);

        UnionChild::where('union_id', $union->id)->first()->delete();

        $this->assertSame(2, $union->fresh()->children_count);
    }

    public function test_birth_order_defaults_to_the_next_position(): void
    {
        [$a, $b] = Person::factory()->count(2)->create()->all();
        $union = Union::factory()->between($a, $b)->create();

        $orders = Person::factory()->count(3)->create()->map(
            fn (Person $child) => UnionChild::create([
                'union_id' => $union->id,
                'person_id' => $child->id,
            ])->birth_order
        );

        $this->assertSame([1, 2, 3], $orders->all());
    }
}
