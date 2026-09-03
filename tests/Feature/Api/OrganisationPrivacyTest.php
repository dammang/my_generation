<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tribe's structure is not neutral metadata.
 *
 * A family branch is a named lineage with a population count and an apical
 * ancestor. An unscoped listing hands an outsider the shape of a whole tribe —
 * every family in it, how large each is, who each descends from — without ever
 * touching a person record. The people were protected; the skeleton was not.
 */
class OrganisationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $private;

    private Tribe $open;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->private = Tribe::factory()->create([
            'name' => 'Closed Tribe',
            'default_privacy_level' => PrivacyLevel::Tribe,
        ]);

        $this->open = Tribe::factory()->create([
            'name' => 'Open Tribe',
            'default_privacy_level' => PrivacyLevel::Public,
        ]);
    }

    private function clanIn(Tribe $tribe, string $name): Clan
    {
        return Clan::factory()->create(['tribe_id' => $tribe->id, 'name' => $name]);
    }

    private function branchIn(Clan $clan, string $name): FamilyBranch
    {
        return FamilyBranch::factory()->create([
            'tribe_id' => $clan->tribe_id,
            'clan_id' => $clan->id,
            'name' => $name,
        ]);
    }

    private function memberOf(Tribe $tribe): User
    {
        $user = User::factory()->create();

        $scope = Scope::where('scopeable_type', 'tribe')
            ->where('scopeable_id', $tribe->id)
            ->firstOrFail();

        Membership::create([
            'user_id' => $user->id,
            'scope_id' => $scope->id,
            'status' => MembershipStatus::Active,
        ]);

        return $user;
    }

    public function test_an_outsider_cannot_enumerate_a_private_tribes_clans(): void
    {
        $this->clanIn($this->private, 'Sui Clan');
        $this->clanIn($this->open, 'Hlei Clan');

        $names = $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.clans.index'))
            ->assertOk()
            ->json('data.*.name');

        $this->assertNotContains('Sui Clan', $names);
        // A tribe that has declared itself public stays browsable — that is
        // how somebody finds the family they belong to before joining.
        $this->assertContains('Hlei Clan', $names);
    }

    public function test_a_member_sees_their_own_tribes_clans(): void
    {
        $this->clanIn($this->private, 'Sui Clan');

        $names = $this->actingAs($this->memberOf($this->private))
            ->getJson(route('api.v1.clans.index'))
            ->assertOk()
            ->json('data.*.name');

        $this->assertContains('Sui Clan', $names);
    }

    public function test_an_outsider_cannot_enumerate_family_branches(): void
    {
        // The most revealing listing: a branch names a lineage and counts it.
        $clan = $this->clanIn($this->private, 'Sui Clan');
        $this->branchIn($clan, 'Thang Lineage');

        $names = $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.branches.index'))
            ->assertOk()
            ->json('data.*.name');

        $this->assertNotContains('Thang Lineage', $names);
    }

    public function test_a_direct_link_is_no_way_around_the_listing(): void
    {
        $clan = $this->clanIn($this->private, 'Sui Clan');
        $branch = $this->branchIn($clan, 'Thang Lineage');

        $outsider = User::factory()->create();

        // 404 rather than 403: a 403 confirms it exists, which on a private
        // lineage is itself the disclosure.
        $this->actingAs($outsider)
            ->getJson(route('api.v1.clans.show', $clan))
            ->assertNotFound();

        $this->actingAs($outsider)
            ->getJson(route('api.v1.branches.show', $branch))
            ->assertNotFound();
    }

    public function test_the_nested_listings_are_scoped_too(): void
    {
        $clan = $this->clanIn($this->open, 'Hlei Clan');
        $this->branchIn($clan, 'Open Lineage');

        $hidden = $this->clanIn($this->private, 'Sui Clan');
        $this->branchIn($hidden, 'Thang Lineage');

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson(route('api.v1.tribes.clans', $this->private))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($outsider)
            ->getJson(route('api.v1.clans.branches', $clan))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Open Lineage');
    }

    public function test_a_super_admin_sees_everything(): void
    {
        $this->clanIn($this->private, 'Sui Clan');

        // The flag, not the role name: ViewerScope reads the column.
        $admin = User::factory()->create(['is_super_admin' => true]);

        $names = $this->actingAs($admin)
            ->getJson(route('api.v1.clans.index'))
            ->assertOk()
            ->json('data.*.name');

        $this->assertContains('Sui Clan', $names);
    }
}
