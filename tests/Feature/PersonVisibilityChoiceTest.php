<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Somebody choosing who may see them.
 *
 * The archive exists to be read, so every restriction here is a deliberate
 * exception and each one has to hold at every door: the record itself, the
 * listing, and the search. A setting that hides a name on one screen and
 * prints it on the next is worse than no setting, because it was believed.
 */
class PersonVisibilityChoiceTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // The viewer's entitlements are cached by user id, and the database is
        // rebuilt between tests with the ids starting again at one — so one
        // test's stranger is served to the next test's cousin.
        Cache::flush();

        $this->tribe = Tribe::factory()->create([
            'default_privacy_level' => PrivacyLevel::Clan,
        ]);
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
    }

    public function test_a_person_can_choose_who_sees_them_without_permission_to_edit(): void
    {
        $person = $this->person();
        $owner = $this->accountFor($person);

        // No people.update anywhere: the whole point is that a decision about
        // being seen does not wait on a reviewer.
        $this->actingAs($owner)
            ->patchJson(route('api.v1.people.visibility', $person), [
                'privacy_level' => PrivacyLevel::Private->value,
            ])
            ->assertOk();

        $this->assertSame(PrivacyLevel::Private, $person->fresh()->privacyLevel());
    }

    public function test_nobody_else_may_choose_it_for_them(): void
    {
        $person = $this->person();

        // Somebody who can see the record perfectly well: refusing here is
        // about who the decision belongs to, not about who may look.
        $clansman = $this->member(inClan: true);

        $this->actingAs($clansman)
            ->patchJson(route('api.v1.people.visibility', $person), [
                'privacy_level' => PrivacyLevel::Public->value,
            ])
            ->assertForbidden();

        $this->assertSame(PrivacyLevel::Clan, $person->fresh()->privacyLevel());
    }

    public function test_a_hidden_living_person_is_a_position_without_a_name(): void
    {
        $person = $this->person(['privacy_level' => PrivacyLevel::Private]);
        $stranger = $this->member();

        // Not 403: saying "forbidden" tells a stranger the record exists.
        $this->actingAs($stranger)
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound();

        $listed = $this->actingAs($stranger)
            ->getJson(route('api.v1.people.index'))
            ->assertOk()
            ->json('data');

        $this->assertNotContains(
            $person->display_name,
            collect($listed)->pluck('display_name')->all(),
            'a hidden person was named in the listing',
        );

        // Searched for by their exact name, which is how somebody would
        // actually be found. The predicate is in the WHERE clause, so this is
        // the same door as the listing — asserted anyway, because a setting
        // that holds on one screen and not the next is worse than none.
        $found = $this->actingAs($stranger)
            ->getJson(route('api.v1.people.index', ['q' => $person->display_name]))
            ->assertOk()
            ->json('data');

        $this->assertSame([], $found, 'a hidden person was findable by name');
    }

    public function test_the_choice_lifts_when_a_death_is_recorded(): void
    {
        $person = $this->person(
            ['privacy_level' => PrivacyLevel::Private],
            born: 1920,
            died: 1998,
        );

        // A genealogy is read generations after it is written; a permanent
        // lock would leave the tree full of nodes nobody can ever read. The
        // archive's own default applies from then on.
        $reader = $this->member(inClan: true);

        $this->actingAs($reader)
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk()
            ->assertJsonPath('data.display_name', $person->display_name);
    }

    public function test_lifting_never_makes_a_record_stricter_than_the_person_chose(): void
    {
        // Public in life, public after death: this relaxes a restriction, it
        // does not impose one. The tribe defaults to clan-only.
        $person = $this->person(
            ['privacy_level' => PrivacyLevel::Public],
            born: 1920,
            died: 1998,
        );

        $this->actingAs($this->member())
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk()
            ->assertJsonPath('data.display_name', $person->display_name);
    }

    public function test_a_living_persons_choice_still_stands(): void
    {
        $person = $this->person(['privacy_level' => PrivacyLevel::Private]);

        $this->actingAs($this->member(inClan: true))
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound();
    }

    public function test_close_family_reaches_a_first_cousin(): void
    {
        // The old scope reached every aunt and uncle and not one cousin, while
        // its own comment said otherwise.
        [$person, $cousin] = $this->cousins();

        $viewer = $this->accountFor($cousin);
        $person->forceFill(['privacy_level' => PrivacyLevel::Family])->save();

        $this->actingAs($viewer)
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk()
            ->assertJsonPath('data.display_name', $person->display_name);
    }

    public function test_close_family_stops_somewhere(): void
    {
        // Everybody in a clan is a cousin at some degree. If the scope reached
        // all of them, "close family" would mean the same as "the clan".
        [$person] = $this->cousins();
        $distant = $this->person();

        $person->forceFill(['privacy_level' => PrivacyLevel::Family])->save();

        $this->actingAs($this->accountFor($distant))
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound();
    }

    /** @return array{0: Person, 1: Person} a person and their first cousin */
    private function cousins(): array
    {
        $grandparent = $this->person(born: 1900);
        $parent = $this->person(born: 1930);
        $aunt = $this->person(born: 1932);
        $person = $this->person(born: 1960);
        $cousin = $this->person(born: 1962);

        Relationship::factory()->parentChild($grandparent, $parent)->create();
        Relationship::factory()->parentChild($grandparent, $aunt)->create();
        Relationship::factory()->parentChild($parent, $person)->create();
        Relationship::factory()->parentChild($aunt, $cousin)->create();

        return [$person, $cousin];
    }

    /**
     * @param  array<string, mixed>  $overrides
     *
     * Born and died through the factory states: both year columns are derived
     * from the date columns on every save, so setting them directly writes a
     * value the next save throws away.
     */
    private function person(array $overrides = [], int $born = 1990, ?int $died = null): Person
    {
        $factory = Person::factory()->bornExactly($born);

        if ($died !== null) {
            $factory = $factory->deceased($died);
        }

        return $factory->create([
            'tribe_id' => $this->tribe->id,
            'clan_id' => $this->clan->id,
            'privacy_level' => PrivacyLevel::Clan,
            ...$overrides,
        ]);
    }

    private function accountFor(Person $person): User
    {
        $user = $this->member(inClan: true);

        $user->forceFill(['person_id' => $person->id])->save();

        return $user;
    }

    private function member(bool $inClan = false): User
    {
        $user = User::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => Scope::where('scopeable_type', $inClan ? 'clan' : 'tribe')
                ->where('scopeable_id', $inClan ? $this->clan->id : $this->tribe->id)
                ->value('id'),
            'status' => MembershipStatus::Active,
        ]);

        return $user;
    }
}
