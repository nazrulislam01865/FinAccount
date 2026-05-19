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
            $payload = [
                'description' => $definition['description'] ?? null,
                'status' => 'Active',
            ];

            if (Schema::hasColumn('roles', 'level')) {
                $payload['level'] = $definition['level'] ?? 99;
            }

            if (Schema::hasColumn('roles', 'is_protected')) {
                $payload['is_protected'] = $definition['protected'] ?? false;
            }

            $role = Role::updateOrCreate(['name' => $roleName], $payload);
            $configuredPermissions = $rolePermissions[$roleName] ?? [];

            $ids = in_array('*', $configuredPermissions, true)
                ? $allPermissionIds
                : collect($configuredPermissions)
                    ->map(fn (string $permission) => $permissionModels[$permission]->id ?? null)
                    ->filter()
                    ->values()
                    ->all();

            $role->permissions()->sync($ids);
        }
    }
}
