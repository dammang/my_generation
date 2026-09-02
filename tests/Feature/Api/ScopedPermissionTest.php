<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Models\User;
use App\Policies\PersonPolicy;
use App\Services\Permissions\PermissionResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authority flows downward by prefix-matching the materialised scopes.path.
 * A Tribe Admin needs no row per clan, and a Clan Admin must not acquire one
 * for the tribe above.
 */
class ScopedPermissionTest extends TestCase
{
    use RefreshDatabase;

    private PermissionResolver $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->permissions = app(PermissionResolver::class);
    }

    private function grant(User $user, string $role, string $type, int $id): void
    {
        $scope = Scope::where('scopeable_type', $type)->where('scopeable_id', $id)->firstOrFail();

        DB::table('scope_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::findByName($role)->id,
            'scope_id' => $scope->id,
            'granted_at' => now(),
        ]);

        $this->permissions->forget($user);
    }

    private function pathOf(string $type, int $id): string
    {
        return Scope::where('scopeable_type', $type)->where('scopeable_id', $id)->value('path');
    }

    public function test_a_tribe_admin_administers_every_clan_beneath(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $branch = FamilyBranch::factory()->create(['tribe_id' => $tribe->id, 'clan_id' => $clan->id]);

        $user = User::factory()->create();
        $this->grant($user, 'tribe-admin', 'tribe', $tribe->id);

        $this->assertTrue($this->permissions->can($user, 'people.verify', $this->pathOf('clan', $clan->id)));
        $this->assertTrue($this->permissions->can($user, 'people.verify', $this->pathOf('family_branch', $branch->id)));
    }

    public function test_a_clan_admin_does_not_acquire_authority_over_the_tribe(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);

        $user = User::factory()->create();
        $this->grant($user, 'clan-admin', 'clan', $clan->id);

        $this->assertTrue($this->permissions->can($user, 'people.verify', $this->pathOf('clan', $clan->id)));
        $this->assertFalse($this->permissions->can($user, 'people.verify', $this->pathOf('tribe', $tribe->id)));
    }

    public function test_authority_does_not_leak_between_sibling_clans(): void
    {
        $tribe = Tribe::factory()->create();
        $mine = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $theirs = Clan::factory()->create(['tribe_id' => $tribe->id]);

        $user = User::factory()->create();
        $this->grant($user, 'clan-admin', 'clan', $mine->id);

        $this->assertFalse($this->permissions->can($user, 'people.verify', $this->pathOf('clan', $theirs->id)));
    }

    public function test_a_contributor_cannot_verify(): void
    {
        $tribe = Tribe::factory()->create();
        $user = User::factory()->create();
        $this->grant($user, 'contributor', 'tribe', $tribe->id);

        $this->assertTrue($this->permissions->can($user, 'people.create', $this->pathOf('tribe', $tribe->id)));
        $this->assertFalse($this->permissions->can($user, 'people.verify', $this->pathOf('tribe', $tribe->id)));
    }

    public function test_a_historian_verifies_but_does_not_manage_members(): void
    {
        $tribe = Tribe::factory()->create();
        $user = User::factory()->create();
        $this->grant($user, 'historian', 'tribe', $tribe->id);

        $path = $this->pathOf('tribe', $tribe->id);

        $this->assertTrue($this->permissions->can($user, 'people.verify', $path));
        $this->assertTrue($this->permissions->can($user, 'disputes.resolve', $path));
        $this->assertFalse($this->permissions->can($user, 'roles.assign', $path));
        $this->assertFalse($this->permissions->can($user, 'tribes.manage', $path));
    }

    public function test_a_viewer_holds_no_permissions(): void
    {
        $tribe = Tribe::factory()->create();
        $user = User::factory()->create();
        $this->grant($user, 'viewer', 'tribe', $tribe->id);

        $this->assertFalse($this->permissions->can($user, 'people.create', $this->pathOf('tribe', $tribe->id)));
        $this->assertFalse($this->permissions->can($user, 'people.view', $this->pathOf('tribe', $tribe->id)));
    }

    public function test_an_unscoped_record_needs_a_global_permission(): void
    {
        $tribe = Tribe::factory()->create();
        $user = User::factory()->create();
        $this->grant($user, 'tribe-admin', 'tribe', $tribe->id);

        $this->assertFalse($this->permissions->can($user, 'people.verify', null));
    }

    public function test_a_super_admin_passes_every_check(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->assertTrue($this->permissions->can($user, 'anything.at.all', null));
    }

    public function test_a_guest_passes_nothing(): void
    {
        $this->assertFalse($this->permissions->can(null, 'people.view', '/1/'));
    }

    public function test_the_person_policy_honours_a_scoped_grant(): void
    {
        $tribe = Tribe::factory()->create();
        $clan = Clan::factory()->create(['tribe_id' => $tribe->id]);
        $person = Person::factory()->create(['tribe_id' => $tribe->id, 'clan_id' => $clan->id]);

        $admin = User::factory()->create();
        $this->grant($admin, 'clan-admin', 'clan', $clan->id);

        $outsider = User::factory()->create();

        $this->actingAs($admin);
        $this->assertTrue(app(PersonPolicy::class)->verify($admin, $person));

        $this->actingAs($outsider);
        $this->assertFalse(app(PersonPolicy::class)->verify($outsider, $person));
    }
}
