<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Clans\DecideClanRegistration;
use App\Actions\Clans\SubmitClanRegistration;
use App\Enums\MembershipStatus;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What a founder can do with an approved clan.
 *
 * An approved clan is empty: no people, no tree, no starting point. The
 * founder has to be able to record the ancestor it descends from, or the clan
 * is a name with nothing under it and the Add Relative flow has nothing to
 * hang off.
 */
class ClanFoundingTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create();
        $this->founder = $this->member();

        $registration = app(SubmitClanRegistration::class)
            ->handle($this->founder, $this->tribe, ['name' => 'Guite']);

        $this->clan = app(DecideClanRegistration::class)
            ->approve($registration, $this->member('tribe-admin'));

        $this->founder->refresh();
    }

    private function member(?string $role = null): User
    {
        $user = User::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')
                ->where('scopeable_id', $this->tribe->id)
                ->value('id'),
            'status' => MembershipStatus::Active,
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    #[Test]
    public function a_founder_starts_the_tree_with_the_ancestor_it_descends_from(): void
    {
        $created = $this->actingAs($this->founder)
            ->postJson(route('api.v1.people.store'), [
                'display_name' => 'Thawng Dam',
                'gender' => 'male',
                'birth' => 'abt. 1890',
                'clan_ulid' => $this->clan->ulid,
            ])
            ->assertCreated();

        $ulid = $created->json('data.ulid');

        // And the clan records where it begins, which is what every generation
        // beneath is then counted from.
        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.clans.update', $this->clan->ulid), [
                'ancestor_person_ulid' => $ulid,
            ])
            ->assertOk()
            ->assertJsonPath('data.ancestor.ulid', $ulid);

        $this->assertSame(
            Person::where('ulid', $ulid)->value('id'),
            $this->clan->refresh()->ancestor_person_id,
        );
    }

    #[Test]
    public function the_founding_ancestor_has_to_be_in_the_clan(): void
    {
        $stranger = Person::factory()->create(['tribe_id' => $this->tribe->id]);

        // Otherwise the clan's tree begins with somebody in another family,
        // and every generation counted from them is counted from the wrong
        // root — which is not visible anywhere until the numbers are wrong.
        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.clans.update', $this->clan->ulid), [
                'ancestor_person_ulid' => $stranger->ulid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.ancestor_person_ulid.0', 'That person is not in this clan. Add them to it first, or start the tree with somebody who is.');
    }

    #[Test]
    public function nobody_adds_people_to_a_family_they_have_no_standing_in(): void
    {
        $otherTribe = Tribe::factory()->create();
        $elsewhere = Clan::factory()->create(['tribe_id' => $otherTribe->id]);

        // May create people — every contributor may. Not here.
        $this->actingAs($this->founder)
            ->postJson(route('api.v1.people.store'), [
                'display_name' => 'Somebody Else',
                'clan_ulid' => $elsewhere->ulid,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('people', ['display_name' => 'Somebody Else']);
    }

    #[Test]
    public function a_person_with_no_family_yet_is_still_allowed(): void
    {
        // Oral records routinely name somebody long before anybody knows which
        // branch they sit in. Refusing those would be refusing the archive's
        // own material.
        $this->actingAs($this->founder)
            ->postJson(route('api.v1.people.store'), ['display_name' => 'Unplaced Ancestor'])
            ->assertCreated();
    }
}
