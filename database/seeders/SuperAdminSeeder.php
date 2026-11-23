<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /** Creates or updates the Super Admin from SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD. */
    public function run(): void
    {
        $email = config('gymai.super_admin.email');
        $password = config('gymai.super_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set — skipping Super Admin.');

            return;
        }

        $user = User::firstOrNew(['email' => strtolower($email)]);
        $user->name = config('gymai.super_admin.name', 'Platform Admin');
        $user->password = $password;
        $user->status = 'active';
        $user->email_verified_at ??= now();
        $user->gym_id = null;
        $user->save();

        $user->syncRoles([Role::SuperAdmin->value]);

        $this->command?->info("Super Admin ready: {$user->email}");
    }
}
