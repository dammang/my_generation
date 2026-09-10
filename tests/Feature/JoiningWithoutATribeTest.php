<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Access\DecideMembership;
use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Privacy\ViewerScopeResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Joining a clan without joining a tribe first.
 *
 * A clan is only visible to somebody already in its tribe, and a clan has no
 * clan_id of its own, so belonging to one matched nothing. The result was a
 * joining screen that listed no clans for anybody who had not already joined
 * the tribe — empty for exactly the people it exists for.
 */
class JoiningWithoutATribeTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Cache::flush();

        // Not public: the tribe keeps its records to the clan, which is the
        // setting a real archive runs with.
        $this->tribe = Tribe::factory()->create([
            'default_privacy_level' => PrivacyLevel::Clan,
        ]);
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
    }

    public function test_somebody_who_belongs_to_nothing_can_see_a_clan_to_ask_for(): void
    {
        $newcomer = User::factory()->create();

        $listed = $this->actingAs($newcomer)
            ->getJson(route('api.v1.clans.index', ['joinable' => 1]))
            ->assertOk()
            ->json('data');

        $this->assertSame(
            [$this->clan->name],
            collect($listed)->pluck('name')->all(),
            'the joining list was empty, so there was nothing to ask for',
        );
    }

    public function test_the_ordinary_listing_still_keeps_the_shape_of_a_tribe_private(): void
    {
        // The joinable list is a deliberate exception, not a hole: without the
        // flag an outsider still sees nothing.
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.clans.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_member_can_see_the_clan_they_were_let_into(): void
    {
        $member = $this->approvedIntoTheClan();

        $this->actingAs($member)
            ->getJson(route('api.v1.clans.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', $this->clan->name);
    }

    public function test_being_let_into_a_clan_is_being_let_into_its_tribe(): void
    {
        // Nobody joins the Zomi in order to join JK. Without this a member sat
        // outside everything counted at tribe level while plainly belonging.
        $member = $this->approvedIntoTheClan();

        $scope = app(ViewerScopeResolver::class)->resolve($member);

        $this->assertSame([$this->clan->id], $scope->clanIds);
        $this->assertSame([$this->tribe->id], $scope->tribeIds);
    }

    public function test_a_rejected_request_carries_nothing(): void
    {
        $applicant = User::factory()->create();

        $membership = Membership::create([
            'user_id' => $applicant->id,
            'scope_id' => $this->clanScope()->id,
            'status' => MembershipStatus::Pending,
        ]);

        app(DecideMembership::class)->handle(
            $membership,
            MembershipStatus::Rejected,
            User::factory()->create(['is_super_admin' => true]),
        );

        $scope = app(ViewerScopeResolver::class)->resolve($applicant);

        $this->assertSame([], $scope->tribeIds);
        $this->assertSame([], $scope->clanIds);
    }

    private function approvedIntoTheClan(): User
    {
        $applicant = User::factory()->create();

        $membership = Membership::create([
            'user_id' => $applicant->id,
            'scope_id' => $this->clanScope()->id,
            'status' => MembershipStatus::Pending,
        ]);

        app(DecideMembership::class)->handle(
            $membership,
            MembershipStatus::Active,
            User::factory()->create(['is_super_admin' => true]),
        );

        return $applicant->fresh();
    }

    private function clanScope(): Scope
    {
        return Scope::where('scopeable_type', 'clan')
            ->where('scopeable_id', $this->clan->id)
            ->firstOrFail();
    }
}
