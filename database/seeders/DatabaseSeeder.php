<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,   // roles and permissions first — SuperAdminSeeder needs them
            EventTypeSeeder::class,
            PlaceSeeder::class,
            SuperAdminSeeder::class,
            DemoTribeSeeder::class,        // local/testing only; skips itself elsewhere
        ]);
    }
}
