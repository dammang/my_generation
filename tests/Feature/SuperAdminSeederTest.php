<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Reading through config is what makes this work on a deployed server.
     *
     * A deployed app runs config:cache, and Laravel then stops reading .env
     * entirely — env() answers with its default. Reading the password directly
     * from env() meant the seeder announced it was missing while it sat in the
     * server's .env, and quietly created no administrator at all.
     */
    public function test_the_password_is_read_from_config_not_the_environment(): void
    {
        config([
            'super_admin.email' => 'admin@khanggui.com',
            'super_admin.name' => 'Dam Mang',
            'super_admin.password' => 'a-real-one-9',
        ]);

        $this->seed(SuperAdminSeeder::class);

        $admin = User::where('email', 'admin@khanggui.com')->firstOrFail();
        $this->assertTrue($admin->is_super_admin);
        $this->assertTrue(Hash::check('a-real-one-9', $admin->password));
        $this->assertTrue($admin->hasRole('super-admin'));
    }

    public function test_running_it_twice_updates_rather_than_duplicates(): void
    {
        config(['super_admin.email' => 'admin@khanggui.com', 'super_admin.password' => 'a-real-one-9']);

        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        // A deploy script runs seeders on every release.
        $this->assertSame(1, User::where('email', 'admin@khanggui.com')->count());
    }
}
