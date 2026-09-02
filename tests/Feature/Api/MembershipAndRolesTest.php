<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MembershipStatus;
use App\Enums\PrivacyLevel;
use App\Models\AuditLog;
use App\Models\Clan;
use App\Models\Membership;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScopeResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MembershipAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private Tribe $tribe;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tribe = Tribe::factory()->create();
        $this->clan = Clan::factory()->create(['tribe_id' => $this->tribe->id]);
    }

    private function grant(User $user, string $role, string $type, int $id): void
    {
        $scope = Scope::where('scopeable_type', $type)->where('scopeable_id', $id)->firstOrFail();

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::findByName($role, 'web')->id,
            'scope_id' => $scope->id,
            'granted_at' => now(),
        ]);

        app(PermissionResolver::class)->forget($user);
        app(ViewerScopeResolver::class)->forget($user);
    }

    // ── Membership ───────────────────────────────────────────────────────

    public function test_requesting_membership_creates_a_pending_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.memberships.store'), [
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', MembershipStatus::Pending->value);
    }

    public function test_a_pending_membership_grants_no_visibility(): void
    {
        // Pending means pending. Until somebody approves it, the applicant sees
        // exactly what a stranger sees.
        $user = User::factory()->create();
        $person = Person::factory()->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Tribe,
        ]);

        $this->actingAs($user)->postJson(route('api.v1.memberships.store'), [
            'scope_type' => 'tribe', 'scope_ulid' => $this->tribe->ulid,
        ])->assertCreated();

        $this->actingAs($user)
            ->getJson(route('api.v1.people.show', $person))
            ->assertNotFound();
    }

    public function test_approving_a_membership_immediately_widens_visibility(): void
    {
        $admin = User::factory()->create();
        $this->grant($admin, 'tribe-admin', 'tribe', $this->tribe->id);

        $applicant = User::factory()->create();
        $person = Person::factory()->deceased(1961)->create([
            'tribe_id' => $this->tribe->id,
            'privacy_level' => PrivacyLevel::Tribe,
        ]);

        $membership = Membership::create([
            'user_id' => $applicant->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->value('id'),
            'status' => MembershipStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->postJson(route('api.v1.memberships.approve', $membership))
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::Active->value);

        // The cached scope must be busted on approval, not ten minutes later.
        $this->actingAs($applicant)
            ->getJson(route('api.v1.people.show', $person))
            ->assertOk();
    }

    public function test_a_non_administrator_cannot_approve_a_membership(): void
    {
        $membership = Membership::create([
            'user_id' => User::factory()->create()->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->value('id'),
            'status' => MembershipStatus::Pending,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.memberships.approve', $membership))
            ->assertForbidden();
    }

    public function test_a_clan_admin_cannot_approve_a_tribe_membership(): void
    {
        // Authority flows downward, never up.
        $clanAdmin = User::factory()->create();
        $this->grant($clanAdmin, 'clan-admin', 'clan', $this->clan->id);

        $membership = Membership::create([
            'user_id' => User::factory()->create()->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->value('id'),
            'status' => MembershipStatus::Pending,
        ]);

        $this->actingAs($clanAdmin)
            ->postJson(route('api.v1.memberships.approve', $membership))
            ->assertForbidden();
    }

    public function test_a_member_can_leave_without_approval(): void
    {
        $user = User::factory()->create();
        $membership = Membership::create([
            'user_id' => $user->id,
            'scope_id' => Scope::where('scopeable_type', 'tribe')->where('scopeable_id', $this->tribe->id)->value('id'),
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('api.v1.memberships.destroy', $membership))
            ->assertNoContent();

        $this->assertSame(MembershipStatus::Left, $membership->fresh()->status);
    }

    public function test_the_member_list_is_visible_only_to_administrators(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.memberships.scope', [
                'scope_type' => 'tribe', 'scope_ulid' => $this->tribe->ulid,
            ]))
            ->assertForbidden();
    }

    // ── Scoped roles ─────────────────────────────────────────────────────

    public function test_a_tribe_admin_can_grant_a_role_in_their_tribe(): void
    {
        $admin = User::factory()->create();
        $this->grant($admin, 'tribe-admin', 'tribe', $this->tribe->id);
        $subject = User::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('api.v1.scope-roles.store'), [
                'user_ulid' => $subject->ulid,
                'scope_type' => 'clan',
                'scope_ulid' => $this->clan->ulid,
                'role' => 'contributor',
            ])
            ->assertOk();

        $this->assertDatabaseHas('scope_role_user', [
            'user_id' => $subject->id,
            'role_id' => Role::findByName('contributor', 'web')->id,
        ]);
    }

    public function test_a_grant_cannot_exceed_the_granters_own_authority(): void
    {
        // The escalation that matters: a family admin with roles.assign minting
        // a tribe admin and escaping their own scope in one call.
        $familyAdmin = User::factory()->create();
        $this->grant($familyAdmin, 'family-admin', 'clan', $this->clan->id);
        $subject = User::factory()->create();

        $this->actingAs($familyAdmin)
            ->postJson(route('api.v1.scope-roles.store'), [
                'user_ulid' => $subject->ulid,
                'scope_type' => 'clan',
                'scope_ulid' => $this->clan->ulid,
                'role' => 'tribe-admin',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'ROLE_ASSIGNMENT_FORBIDDEN');

        $this->assertDatabaseMissing('scope_role_user', [
            'user_id' => $subject->id,
            'role_id' => Role::findByName('tribe-admin', 'web')->id,
        ]);
    }

    public function test_super_admin_cannot_be_granted_from_a_scoped_endpoint(): void
    {
        // It is a global bypass; a scoped endpoint must not be able to mint one.
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('api.v1.scope-roles.store'), [
                'user_ulid' => User::factory()->create()->ulid,
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
                'role' => 'super-admin',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['role']]);
    }

    public function test_a_user_without_roles_assign_cannot_grant_anything(): void
    {
        $contributor = User::factory()->create();
        $this->grant($contributor, 'contributor', 'tribe', $this->tribe->id);

        $this->actingAs($contributor)
            ->postJson(route('api.v1.scope-roles.store'), [
                'user_ulid' => User::factory()->create()->ulid,
                'scope_type' => 'tribe',
                'scope_ulid' => $this->tribe->ulid,
                'role' => 'contributor',
            ])
            ->assertForbidden();
    }

    public function test_a_granted_role_takes_effect_immediately(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $subject = User::factory()->create();

        $this->actingAs($admin)->postJson(route('api.v1.scope-roles.store'), [
            'user_ulid' => $subject->ulid,
            'scope_type' => 'clan',
            'scope_ulid' => $this->clan->ulid,
            'role' => 'clan-admin',
        ])->assertOk();

        $path = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $this->clan->id)->value('path');

        $this->assertTrue(app(PermissionResolver::class)->can($subject->fresh(), 'people.verify', $path));
    }

    public function test_revoking_a_role_removes_the_grant(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $subject = User::factory()->create();
        $this->grant($subject, 'clan-admin', 'clan', $this->clan->id);

        $this->actingAs($admin)
            ->deleteJson(route('api.v1.scope-roles.destroy'), [
                'user_ulid' => $subject->ulid,
                'scope_type' => 'clan',
                'scope_ulid' => $this->clan->ulid,
                'role' => 'clan-admin',
            ])
            ->assertNoContent();

        $path = Scope::where('scopeable_type', 'clan')->where('scopeable_id', $this->clan->id)->value('path');

        $this->assertFalse(app(PermissionResolver::class)->can($subject->fresh(), 'people.verify', $path));
    }

    public function test_role_grants_are_written_to_the_audit_log(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $subject = User::factory()->create();

        $this->actingAs($admin)->postJson(route('api.v1.scope-roles.store'), [
            'user_ulid' => $subject->ulid,
            'scope_type' => 'tribe',
            'scope_ulid' => $this->tribe->ulid,
            'role' => 'historian',
        ])->assertOk();

        $log = AuditLog::where('action', 'role.granted')->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('historian', $log->context['role']);
    }
}
