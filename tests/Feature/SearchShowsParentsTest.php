<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PrivacyLevel;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Tribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Telling one Thawng from another.
 *
 * A search for "thaw" returns a column of names with "No dates recorded"
 * under every one of them, which is no help at all in a family where a dozen
 * people share a given name. The parents are what distinguishes them.
 */
class SearchShowsParentsTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tribe = Tribe::factory()->create();
        $this->viewer = User::factory()->create(['is_super_admin' => true]);
    }

    public function test_a_search_result_names_the_parents(): void
    {
        $child = $this->person('THAWNG DAM');

        Relationship::factory()->parentChild($this->person('HAU NENG', Gender::Male), $child)->create();
        Relationship::factory()->parentChild($this->person('DIM ZEL', Gender::Female), $child)->create();

        $this->actingAs($this->viewer)
            ->getJson(route('api.v1.people.index', ['q' => 'THAWNG']))
            ->assertOk()
            ->assertJsonPath('data.0.parents.father', 'HAU NENG')
            ->assertJsonPath('data.0.parents.mother', 'DIM ZEL');
    }

    public function test_a_person_with_no_parents_recorded_carries_none(): void
    {
        $this->person('THAWNG DAM');

        $this->actingAs($this->viewer)
            ->getJson(route('api.v1.people.index', ['q' => 'THAWNG']))
            ->assertOk()
            ->assertJsonPath('data.0.parents', []);
    }

    public function test_a_parent_the_reader_may_not_see_is_not_named(): void
    {
        // A parent is a person. Somebody who may not be seen is not named
        // here either, however visible their child is — otherwise a search
        // result becomes a way to read names the archive is withholding.
        $child = $this->person('THAWNG DAM', privacy: PrivacyLevel::Public);
        $hidden = $this->person('HAU NENG', Gender::Male, PrivacyLevel::Private);

        Relationship::factory()->parentChild($hidden, $child)->create();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson(route('api.v1.people.index', ['q' => 'THAWNG']))
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'THAWNG DAM')
            ->assertJsonMissingPath('data.0.parents.father');
    }

    public function test_a_parent_whose_sex_was_never_recorded_is_left_out(): void
    {
        // Rather than guessed at: "Father" about somebody nobody recorded as a
        // man is an invention, and this is a record.
        $child = $this->person('THAWNG DAM');

        Relationship::factory()
            ->parentChild($this->person('PAU', Gender::Unknown), $child)
            ->create();

        $this->actingAs($this->viewer)
            ->getJson(route('api.v1.people.index', ['q' => 'THAWNG']))
            ->assertOk()
            ->assertJsonPath('data.0.parents', []);
    }

    private function person(
        string $name,
        Gender $gender = Gender::Male,
        PrivacyLevel $privacy = PrivacyLevel::Public,
    ): Person {
        return Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'display_name' => $name,
            'first_name' => $name,
            'last_name' => null,
            'gender' => $gender,
            'privacy_level' => $privacy,
        ]);
    }
}
