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
use Illuminate\Support\Facades\DB;
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

    #[Test]
    public function a_family_branch_makes_generations_appear_at_once(): void
    {
        $ancestor = $this->founding('Pu Zo');

        // A child, through the app's own path rather than hand-built rows, so
        // what is counted is what the archive actually holds.
        $this->actingAs($this->founder)
            ->postJson(route('api.v1.people.relatives', $ancestor), [
                'relation' => 'son',
                'person' => ['display_name' => 'Thang Zo'],
            ])
            ->assertCreated();

        // Before: 104 people and no numbers is what a clan with no branch
        // looks like — nothing declares where counting begins.
        $this->assertSame(0, DB::table('lineage_depths')->count());

        $this->actingAs($this->founder)
            ->postJson(route('api.v1.branches.store'), [
                'tribe_ulid' => $this->tribe->ulid,
                'clan_ulid' => $this->clan->ulid,
                'name' => 'Zo line',
                'ancestor_person_ulid' => $ancestor->ulid,
            ])
            ->assertCreated();

        // At once, not on the next hourly run: somebody who has just said
        // where their family begins and sees no change concludes it failed.
        $son = Person::where('display_name', 'Thang Zo')->firstOrFail();

        $this->assertSame(0, (int) DB::table('lineage_depths')
            ->where('person_id', $ancestor->id)->value('depth'));
        $this->assertSame(1, (int) DB::table('lineage_depths')
            ->where('person_id', $son->id)->value('depth'));

        $this->actingAs($this->founder)
            ->getJson(route('api.v1.people.show', $son))
            ->assertOk()
            ->assertJsonPath('data.generation_label', '2nd Generation');
    }

    #[Test]
    public function a_branch_cannot_be_put_in_a_family_the_founder_does_not_run(): void
    {
        $otherTribe = Tribe::factory()->create();

        $this->actingAs($this->founder)
            ->postJson(route('api.v1.branches.store'), [
                'tribe_ulid' => $otherTribe->ulid,
                'name' => 'Somebody else\'s line',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function a_generation_can_be_assigned_by_hand_and_wins(): void
    {
        $ancestor = $this->founding('Pu Zo');

        // A clan numbering its own people. Tribe authority is not required for
        // a label that only describes this clan.
        $generation = $this->actingAs($this->founder)
            ->postJson(route('api.v1.generations.store'), [
                'tribe_ulid' => $this->tribe->ulid,
                'clan_ulid' => $this->clan->ulid,
                'generation_number' => 4,
                'generation_name' => '4th Generation',
            ])
            ->assertCreated()
            ->json('data.ulid');

        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.people.update', $ancestor), [
                'generation_ulid' => $generation,
            ])
            ->assertSuccessful();

        // Somebody who married in is counted at their partner's generation,
        // not at their own distance from a founder this archive may not hold,
        // so a hand-assigned label outranks anything derived.
        $this->actingAs($this->founder)
            ->getJson(route('api.v1.people.show', $ancestor))
            ->assertOk()
            ->assertJsonPath('data.generation_label', '4th Generation');
    }

    /** The first person in the clan, created the way the app creates them. */
    private function founding(string $name): Person
    {
        $ulid = $this->actingAs($this->founder)
            ->postJson(route('api.v1.people.store'), [
                'display_name' => $name,
                'clan_ulid' => $this->clan->ulid,
            ])
            ->assertCreated()
            ->json('data.ulid');

        return Person::where('ulid', $ulid)->firstOrFail();
    }

    #[Test]
    public function a_clan_counts_from_its_own_origin_and_still_knows_the_older_scale(): void
    {
        // Pu Zo — Kham — Jasuan — Thang. The clan descends from Pu Zo but
        // counts from Jasuan, which is how a family that can name an ancestor
        // eleven generations back still says "first generation of Jasuan".
        $puZo = $this->founding('Pu Zo');
        $kham = $this->addChild($puZo, 'Kham');
        $jasuan = $this->addChild($kham, 'Jasuan');
        $thang = $this->addChild($jasuan, 'Thang');

        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.clans.update', $this->clan->ulid), [
                'ancestor_person_ulid' => $puZo->ulid,
                'counting_origin_person_ulid' => $jasuan->ulid,
            ])
            ->assertOk();

        // The origin is the first generation of its own scale and the third
        // on the older one. Both numbers are true and families use both.
        $this->assertGeneration($jasuan, '1st Generation', [
            'number' => 1,
            'origin' => 'Jasuan',
            'outer_number' => 3,
            'outer_origin' => 'Pu Zo',
        ]);

        $this->assertGeneration($thang, '2nd Generation', [
            'number' => 2,
            'outer_number' => 4,
        ]);

        // Above the origin. They are not the zeroth or minus-first generation
        // of anything — they are the people the counting starts after, and
        // before this they carried no generation at all.
        $this->assertGeneration($kham, 'Pre-generation 1', [
            'before_origin' => 1,
            'outer_number' => 2,
        ]);

        $this->assertGeneration($puZo, 'Pre-generation 2', [
            'before_origin' => 2,
            'outer_number' => 1,
        ]);
    }

    #[Test]
    public function moving_the_origin_renumbers_everybody_at_once(): void
    {
        $puZo = $this->founding('Pu Zo');
        $kham = $this->addChild($puZo, 'Kham');

        $this->setScale(ancestor: $puZo, origin: $puZo);
        $this->assertGeneration($kham, '2nd Generation', ['number' => 2]);

        // The same person, one setting later. Nothing about the graph changed;
        // what changed is where the family says counting begins.
        $this->setScale(ancestor: $puZo, origin: $kham);
        $this->assertGeneration($kham, '1st Generation', ['number' => 1]);
        $this->assertGeneration($puZo, 'Pre-generation 1', ['before_origin' => 1]);
    }

    private function setScale(Person $ancestor, Person $origin): void
    {
        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.clans.update', $this->clan->ulid), [
                'ancestor_person_ulid' => $ancestor->ulid,
                'counting_origin_person_ulid' => $origin->ulid,
            ])
            ->assertOk();
    }

    private function addChild(Person $parent, string $name): Person
    {
        $ulid = $this->actingAs($this->founder)
            ->postJson(route('api.v1.people.relatives', $parent), [
                'relation' => 'son',
                'person' => ['display_name' => $name],
            ])
            ->assertCreated()
            ->json('data.person.ulid');

        return Person::where('ulid', $ulid)->firstOrFail();
    }

    /** @param  array<string, mixed>  $detail */
    private function assertGeneration(Person $person, string $label, array $detail): void
    {
        $response = $this->actingAs($this->founder)
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk()
            ->assertJsonPath('data.generation_label', $label);

        foreach ($detail as $key => $value) {
            $response->assertJsonPath("data.generation.$key", $value);
        }
    }
}
