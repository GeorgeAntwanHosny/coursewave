<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSyncSeeder extends Seeder
{
    public function run(): void
    {
        $instructorRole = Role::where('name', 'instructor')->where('guard_name', 'web')->first();
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        $allPermissions = Permission::where('guard_name', 'web')->get();

        if ($allPermissions->isEmpty()) {
            $this->command?->warn(
                'No permissions found to sync. Run `php artisan shield:generate --panel=instructor` '
                .'and `--panel=admin` (with --resource=... for each Filament resource) before this seeder.'
            );

            return;
        }

        if ($adminRole) {
            $adminRole->syncPermissions($allPermissions);
            $this->command?->info("Synced {$allPermissions->count()} permissions to 'admin'.");
        }

        if ($instructorRole) {
            $instructorPermissions = $allPermissions->reject(
                fn (Permission $permission) => str_contains($permission->name, 'Plan')
            );
            $instructorRole->syncPermissions($instructorPermissions);
            $this->command?->info("Synced {$instructorPermissions->count()} permissions to 'instructor'.");
        }
    }
}
