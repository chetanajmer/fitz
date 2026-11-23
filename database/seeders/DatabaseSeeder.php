<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe seed: roles, Super Admin, platform defaults.
     * Demo data is opt-in: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            PlatformSeeder::class,
        ]);
    }
}
