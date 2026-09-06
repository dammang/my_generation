<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Clans\DecideClanRegistration;
use App\Actions\Clans\SubmitClanRegistration;
use App\Enums\MembershipStatus;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Appointing a committee.
 *
 * Approving a clan hands it to one person. Everything here is about how that
 * person hands parts of it to anybody else — and what they are refused.
 */
class CommitteeTest extends TestCase
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

        // Through the real flow rather than a hand-made row: the founder's
        // authority is the thing under test, and a fixture that granted it
        // directly would prove only that the fixture works.
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
            'scope_id' => $this->scopeOf('tribe', $this->tribe->id)->id,
            'status' => MembershipStatus::Active,
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function scopeOf(string $type, int $id): Scope
    {
        return Scope::where('scopeable_type', $type)->where('scopeable_id', $id)->firstOrFail();
    }

    /** @return array<string, string> */
    private function scopeQuery(): array
    {
        return ['scope_type' => 'clan', 'scope_ulid' => $this->clan->ulid];
    }

    #[Test]
    public function a_founder_is_told_where_they_may_appoint_and_what_they_may_hand_out(): void
    {
        $response = $this->actingAs($this->founder)
            ->getJson(route('api.v1.scope-roles.administered'))
            ->assertOk();

        $clans = collect($response->json('data'))->where('scope_type', 'clan');

        $this->assertCount(1, $clans, 'the founder should be offered their clan and nothing else');
        $this->assertSame($this->clan->ulid, $clans->first()['scope_ulid']);

        $assignable = $clans->first()['assignable_roles'];

        // Their own role, so a committee can be more than one person.
        $this->assertContains('clan-admin', $assignable);

        // But not the one above them. A clan admin who could mint a tribe
        // admin would be a tribe admin by one extra tap.
        $this->assertNotContains('tribe-admin', $assignable);
    }

    #[Test]
    public function the_pool_reaches_above_the_clan(): void
    {
        $cousin = $this->member();

        $names = collect(
            $this->actingAs($this->founder)
                ->getJson(route('api.v1.scope-roles.candidates', $this->scopeQuery()))
                ->assertOk()
                ->json('data')
        )->pluck('user.ulid');

        // Memberships in this archive are held at the tribe, so a pool limited
        // to the clan's own members would be empty and the committee could
        // never be appointed at all.
        $this->assertContains($cousin->ulid, $names);
    }

    #[Test]
    public function appointing_somebody_puts_them_on_the_committee(): void
    {
        $cousin = $this->member();

        $this->actingAs($this->founder)
            ->postJson(route('api.v1.scope-roles.store'), [
                ...$this->scopeQuery(),
                'user_ulid' => $cousin->ulid,
                'role' => 'historian',
            ])
            ->assertOk();

        $appointments = collect(
            $this->actingAs($this->founder)
                ->getJson(route('api.v1.scope-roles.index', $this->scopeQuery()))
                ->assertOk()
                ->json('data')
        );

        // The founder's own clan-admin row is in this list too, so the one
        // under test is found by who it is about rather than by position.
        $appointed = $appointments->firstWhere('user.ulid', $cousin->ulid);

        $this->assertNotNull($appointed, 'the appointment is missing from the committee');
        $this->assertSame('historian', $appointed['role']);
        $this->assertSame($this->founder->name, $appointed['granted_by']);

        // The grant is authority, not decoration — and authority inside this
        // clan only, which is the half a pivot row alone would not prove.
        $permissions = app(PermissionResolver::class);
        $cousin->refresh();

        $this->assertTrue(
            $permissions->can($cousin, 'people.verify', $this->scopeOf('clan', $this->clan->id)->path),
        );

        $this->assertFalse(
            $permissions->can($cousin, 'people.verify', $this->scopeOf('tribe', $this->tribe->id)->path),
            'an appointment inside a clan must not reach the tribe above it',
        );
    }

    #[Test]
    public function a_clan_admin_cannot_appoint_above_their_own_clan(): void
    {
        $cousin = $this->member();

        // The tribe is not theirs to staff, even though their clan sits in it.
        $this->actingAs($this->founder)
            ->postJson(route('api.v1.scope-roles.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
                'user_ulid' => $cousin->ulid,
                'role' => 'clan-admin',
            ])
            ->assertForbidden();

        // Nor may they hand out a role bigger than their own, even inside it.
        $this->actingAs($this->founder)
            ->postJson(route('api.v1.scope-roles.store'), [
                ...$this->scopeQuery(),
                'user_ulid' => $cousin->ulid,
                'role' => 'tribe-admin',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function who_runs_a_family_is_not_public(): void
    {
        $this->actingAs($this->member())
            ->getJson(route('api.v1.scope-roles.index', $this->scopeQuery()))
            ->assertForbidden();

        $this->actingAs($this->member())
            ->getJson(route('api.v1.scope-roles.administered'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function an_appointment_can_be_taken_back(): void
    {
        $cousin = $this->member();
        $payload = [...$this->scopeQuery(), 'user_ulid' => $cousin->ulid, 'role' => 'contributor'];

        $this->actingAs($this->founder)->postJson(route('api.v1.scope-roles.store'), $payload)->assertOk();
        $this->actingAs($this->founder)->deleteJson(route('api.v1.scope-roles.destroy'), $payload)->assertNoContent();

        $this->actingAs($this->founder)
            ->getJson(route('api.v1.scope-roles.index', $this->scopeQuery()))
            ->assertOk()
            ->assertJsonMissing(['role' => 'contributor']);
    }
}
