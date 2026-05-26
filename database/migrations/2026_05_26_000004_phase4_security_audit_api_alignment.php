<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('audit_logs', 'route_name')) {
                    $table->string('route_name', 160)->nullable()->after('user_agent');
                }

                if (! Schema::hasColumn('audit_logs', 'request_method')) {
                    $table->string('request_method', 12)->nullable()->after('route_name');
                }

                if (! Schema::hasColumn('audit_logs', 'request_url')) {
                    $table->text('request_url')->nullable()->after('request_method');
                }

                if (! Schema::hasColumn('audit_logs', 'metadata')) {
                    $table->json('metadata')->nullable()->after('request_url');
                }
            });
        }

        $this->syncSrsRolesAndPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            foreach (['metadata', 'request_url', 'request_method', 'route_name'] as $column) {
                if (Schema::hasColumn('audit_logs', $column)) {
                    Schema::table('audit_logs', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }

    private function syncSrsRolesAndPermissions(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissionIds = [];

        foreach (config('access.permissions', []) as $name => $label) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $permissionIds[$name] = (int) DB::table('permissions')->where('name', $name)->value('id');
        }

        $allPermissionIds = array_values($permissionIds);
        $rolePermissionConfig = config('access.role_permissions', []);

        foreach (config('access.roles', []) as $roleName => $definition) {
            $rolePayload = [
                'description' => $definition['description'] ?? null,
                'status' => 'Active',
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('roles', 'level')) {
                $rolePayload['level'] = $definition['level'] ?? 99;
            }

            if (Schema::hasColumn('roles', 'is_protected')) {
                $rolePayload['is_protected'] = $definition['protected'] ?? false;
            }

            if (! DB::table('roles')->where('name', $roleName)->exists()) {
                $rolePayload['name'] = $roleName;
                $rolePayload['created_at'] = $now;
                DB::table('roles')->insert($rolePayload);
            } else {
                DB::table('roles')->where('name', $roleName)->update($rolePayload);
            }

            $roleId = (int) DB::table('roles')->where('name', $roleName)->value('id');
            $existingPermissionCount = Schema::hasTable('permission_role')
                ? DB::table('permission_role')->where('role_id', $roleId)->count()
                : 0;

            if (! Schema::hasTable('permission_role')) {
                continue;
            }

            $configured = $rolePermissionConfig[$roleName] ?? [];
            $targetPermissionIds = in_array('*', $configured, true)
                ? $allPermissionIds
                : collect($configured)
                    ->map(fn (string $permission) => $permissionIds[$permission] ?? null)
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

            $isNewSrsAlias = in_array($roleName, ['Manager / Approver', 'Auditor / Viewer', 'Business Owner'], true);
            $isFixedFullAccess = in_array($roleName, ['Super Admin', 'Admin', 'Company Admin'], true);

            if ($isFixedFullAccess || $isNewSrsAlias || $existingPermissionCount === 0) {
                DB::table('permission_role')->where('role_id', $roleId)->delete();

                foreach (array_unique($targetPermissionIds) as $permissionId) {
                    DB::table('permission_role')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
