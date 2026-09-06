<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Scope;
use App\Models\User;
use App\Services\Permissions\PermissionResolver;
use App\Support\DemoCredentials;
use Database\Seeders\DemoPresentationSeeder;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The account the presentation is given from.
 *
 * Its whole purpose is to have something to approve and the standing to
 * approve it. An account called "Demo Admin" that signs in but cannot act is
 * worse than no demo account, because the failure only shows up in front of
 * the audience.
 */
class DemoPresentationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['is_super_admin' => true]);

        // Reference data the demo seeder reads but does not create.
        $this->seed(EventTypeSeeder::class);
    }

    #[Test]
    public function the_demo_admin_can_actually_administer_the_tribe(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoPresentationSeeder::class);

        $admin = User::where('email', DemoCredentials::ADMIN_EMAIL)->firstOrFail();

        // Scoped roles live in scope_role_user, not in Spatie's global roles —
        // getRoleNames() reports "contributor" either way, which is why this
        // asserts on the thing that actually grants the power.
        $this->assertTrue(
            DB::table('scope_role_user')
                ->join('roles', 'roles.id', '=', 'scope_role_user.role_id')
                ->where('scope_role_user.user_id', $admin->getKey())
                ->where('roles.name', 'tribe-admin')
                ->exists(),
            'Demo Admin holds no scoped tribe-admin role',
        );

        $pending = Membership::whereHas(
            'user',
            fn ($q) => $q->where('email', DemoCredentials::APPLICANT_EMAIL)
        )->firstOrFail();

        // The same call the Approve button is gated on.
        $this->assertTrue(
            app(PermissionResolver::class)->administersMembership($admin, $pending->scope->path),
            'Demo Admin cannot approve the membership this seeder created for it',
        );
    }

    #[Test]
    public function it_refuses_to_produce_an_admin_in_name_only(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // Precisely the partial state the old code tolerated: contributor
        // exists, so the seeder gets far enough to build the accounts, but
        // tribe-admin does not. It used to skip the grant quietly and hand
        // back an account that looked right until somebody asked it to do the
        // one thing it exists for.
        Role::where('name', 'tribe-admin')->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tribe-admin role does not exist/');

        $this->seed(DemoPresentationSeeder::class);
    }

    #[Test]
    public function the_demo_tribe_has_something_waiting_to_be_approved(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoPresentationSeeder::class);

        $scope = Scope::where('scopeable_type', 'tribe')->firstOrFail();

        $this->assertDatabaseHas('memberships', [
            'scope_id' => $scope->id,
            'status' => 'pending',
        ]);
    }
}
