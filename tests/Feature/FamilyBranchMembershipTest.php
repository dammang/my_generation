<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Clans\DecideClanRegistration;
use App\Actions\Clans\SubmitClanRegistration;
use App\Enums\MembershipStatus;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who a family branch contains.
 *
 * A branch is the line descending from one person. Getting the founder wrong
 * is easy and looks like nothing: the archive had a branch named after a man
 * ten generations down that was rooted at the clan's own ancestor, so every
 * one of that ancestor's descendants was reported on their own profile as
 * being of that man's family. Nothing anywhere said otherwise.
 */
class FamilyBranchMembershipTest extends TestCase
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
            ->handle($this->founder, $this->tribe, ['name' => 'JK']);

        $this->clan = app(DecideClanRegistration::class)
            ->approve($registration, $this->member('tribe-admin'));

        $this->founder->refresh();
    }

    public function test_a_branch_holds_the_line_below_its_founder_and_nobody_else(): void
    {
        // Pu Zo at the top, Thawng Dam some way down with a family of his own,
        // and a cousin line that is emphatically not his.
        $puZo = $this->firstPerson('Pu Zo');
        $tunKhai = $this->addChild($puZo, 'Tun Khai');
        $thawngDam = $this->addChild($tunKhai, 'Thawng Dam');
        $son = $this->addChild($thawngDam, 'Nang Za Dai');
        $grandson = $this->addChild($son, 'Dam Suan');

        $cousin = $this->addChild($puZo, 'Kip Tun');

        $branch = $this->branchDescendingFrom($puZo, 'Thawng Dam');

        // The mistake, exactly as it stood in the archive: named for one man,
        // rooted at another, and holding everybody underneath the wrong one.
        $this->assertSame(
            [$puZo->id, $tunKhai->id, $thawngDam->id, $son->id, $grandson->id, $cousin->id],
            $this->peopleIn($branch),
        );

        $this->reanchor($branch, $thawngDam);

        $this->assertSame(
            [$thawngDam->id, $son->id, $grandson->id],
            $this->peopleIn($branch),
            'the branch kept people who do not descend from its founder',
        );

        foreach ([$puZo, $tunKhai, $cousin] as $outsider) {
            $this->assertNull(
                $outsider->fresh()->family_branch_id,
                $outsider->display_name.' is still recorded in a family they are not part of',
            );
        }
    }

    public function test_the_branchs_own_count_agrees_with_the_people_in_it(): void
    {
        $puZo = $this->firstPerson('Pu Zo');
        $thawngDam = $this->addChild($puZo, 'Thawng Dam');
        $this->addChild($thawngDam, 'Nang Za Dai');
        $this->addChild($puZo, 'Kip Tun');

        $branch = $this->branchDescendingFrom($puZo, 'Thawng Dam');
        $this->reanchor($branch, $thawngDam);

        // Placing and releasing are mass updates, which fire no observer. The
        // counter is what every screen shows, so a branch of two that says
        // four is wrong everywhere it is read.
        $this->assertSame(
            count($this->peopleIn($branch)),
            $branch->fresh()->people_count,
        );
    }

    public function test_a_branch_that_names_nobody_lets_nobody_go(): void
    {
        $puZo = $this->firstPerson('Pu Zo');
        $son = $this->addChild($puZo, 'Thawng Dam');

        $branch = $this->branchDescendingFrom($puZo, 'Whoever');
        $held = $this->peopleIn($branch);

        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.branches.update', $branch), [
                'ancestor_person_ulid' => null,
            ])
            ->assertOk();

        // A branch making no claim about who founded it cannot show that
        // anybody in it is out of place; emptying it would be a guess.
        $this->assertSame($held, $this->peopleIn($branch));
        $this->assertContains($son->id, $held);
    }

    public function test_letting_somebody_go_does_not_cost_them_their_generation(): void
    {
        // The risk in releasing people: the generation label used to be read
        // through a person's own branch, so emptying a branch they should
        // never have been in could have taken their number with it. It is read
        // from the clan's counting origin, and this is what says so.
        $puZo = $this->firstPerson('Pu Zo');
        $jasuan = $this->addChild($puZo, 'Jasuan');
        $kipTun = $this->addChild($jasuan, 'Kip Tun');
        $thawngDam = $this->addChild($jasuan, 'Thawng Dam');

        $this->countFrom(ancestor: $puZo, origin: $jasuan);

        $branch = $this->branchDescendingFrom($puZo, 'Thawng Dam');
        $this->reanchor($branch, $thawngDam);

        $this->assertNull($kipTun->fresh()->family_branch_id);

        $this->actingAs($this->founder)
            ->getJson(route('api.v1.people.show', $kipTun))
            ->assertOk()
            ->assertJsonPath('data.generation.number', 2)
            ->assertJsonPath('data.generation.origin', 'Jasuan')
            ->assertJsonPath('data.generation.outer_number', 3)
            ->assertJsonPath('data.generation.outer_origin', 'Pu Zo');
    }

    private function countFrom(Person $ancestor, Person $origin): void
    {
        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.clans.update', $this->clan->ulid), [
                'ancestor_person_ulid' => $ancestor->ulid,
                'counting_origin_person_ulid' => $origin->ulid,
            ])
            ->assertOk();
    }

    /** @return list<int> */
    private function peopleIn(FamilyBranch $branch): array
    {
        return Person::where('family_branch_id', $branch->getKey())
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    private function branchDescendingFrom(Person $ancestor, string $name): FamilyBranch
    {
        $ulid = $this->actingAs($this->founder)
            ->postJson(route('api.v1.branches.store'), [
                'tribe_ulid' => $this->tribe->ulid,
                'clan_ulid' => $this->clan->ulid,
                'ancestor_person_ulid' => $ancestor->ulid,
                'name' => $name,
            ])
            ->assertCreated()
            ->json('data.ulid');

        return FamilyBranch::where('ulid', $ulid)->firstOrFail();
    }

    private function reanchor(FamilyBranch $branch, Person $ancestor): void
    {
        $this->actingAs($this->founder)
            ->patchJson(route('api.v1.branches.update', $branch), [
                'ancestor_person_ulid' => $ancestor->ulid,
            ])
            ->assertOk();
    }

    private function firstPerson(string $name): Person
    {
        $ulid = $this->actingAs($this->founder)
            ->postJson(route('api.v1.people.store'), [
                'tribe_ulid' => $this->tribe->ulid,
                'clan_ulid' => $this->clan->ulid,
                'display_name' => $name,
                'gender' => 'male',
            ])
            ->assertCreated()
            ->json('data.ulid');

        return Person::where('ulid', $ulid)->firstOrFail();
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

    private function member(?string $role = null): User
    {
        $user = User::factory()->create();

        // Scoped to the tribe: without it every request is answered as though
        // the archive did not exist, which is what a reader outside a scope is
        // meant to see.
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
}
