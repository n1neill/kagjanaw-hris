<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission and role from the App\Enums definitions.
 * Safe to re-run: role permissions are synced, not appended.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        // Seeders run without model events, so Spatie cannot flush its cache itself.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(
                    fn (Permission $permission): string => $permission->value,
                    $role->permissions(),
                ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
