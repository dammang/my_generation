<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The first administrator.
 *
 * Credentials come from the environment so no password is ever committed. In
 * production, set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD before seeding;
 * the seeder refuses to invent a password outside local development.
 *
 * Read through config rather than env() because a deployed app caches its
 * config, and env() answers with its default once it has.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('super_admin.email');
        $password = config('super_admin.password');

        if ($password === null) {
            if (! app()->environment('local', 'testing')) {
                $this->command?->warn(
                    'SUPER_ADMIN_PASSWORD is not set — skipping super admin creation. '
                    .'Set it in .env and re-run: php artisan db:seed --class=SuperAdminSeeder'
                );

                return;
            }

            $password = 'password';
        }

        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => (string) config('super_admin.name'),
            'password' => Hash::make($password),
        ]);
        $user->email_verified_at = now();
        $user->is_super_admin = true;
        $user->status = UserStatus::Active;
        $user->save();

        $user->syncRoles(['super-admin']);

        $this->command?->info("Super admin ready: {$email}");
    }
}
