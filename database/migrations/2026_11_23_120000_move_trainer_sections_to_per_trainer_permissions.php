<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\TrainerAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Trainer sections become per-trainer grants: the trainer role keeps only the dashboard,
 * and every existing trainer receives the previous role defaults so nothing changes for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();

        User::role(Role::Trainer->value)->whereDoesntHave('permissions')->each(
            fn (User $trainer) => $trainer->givePermissionTo(TrainerAccess::DEFAULTS)
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible data change: role definitions live in App\Enums\Role.
    }
};
