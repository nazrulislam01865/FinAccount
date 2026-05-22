<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = config('access.permissions', []);
        $permissionModels = [];

        foreach ($permissions as $name => $label) {
            $permissionModels[$name] = Permission::updateOrCreate(
                ['name' => $name],
                ['label' => $label]
            );
        }

        $allPermissionIds = collect($permissionModels)->pluck('id')->all();
        $rolePermissions = config('access.role_permissions', []);

        foreach (config('access.roles', []) as $roleName => $definition) {
            $role = Role::firstOrNew(['name' => $roleName]);
            $isNewRole = !$role->exists;

            $role->description = $definition['description'] ?? null;
            $role->status = 'Active';

            if (Schema::hasColumn('roles', 'level')) {
                $role->level = $definition['level'] ?? 99;
            }

            if (Schema::hasColumn('roles', 'is_protected')) {
                $role->is_protected = $definition['protected'] ?? false;
            }

            $role->save();

            $configuredPermissions = $rolePermissions[$roleName] ?? [];

            $ids = in_array('*', $configuredPermissions, true)
                ? $allPermissionIds
                : collect($configuredPermissions)
                    ->map(fn (string $permission) => $permissionModels[$permission]->id ?? null)
                    ->filter()
                    ->values()
                    ->all();

            if ($role->isSuperAdmin()) {
                $role->permissions()->sync($allPermissionIds);
                continue;
            }

            if ($isNewRole || !$role->permissions()->exists()) {
                $role->permissions()->sync($ids);
            }
        }
    }
}
