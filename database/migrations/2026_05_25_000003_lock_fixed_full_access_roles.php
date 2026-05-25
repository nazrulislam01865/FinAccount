<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $allPermissionIds = DB::table('permissions')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach (Role::FIXED_FULL_ACCESS_ROLES as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (!$roleId) {
                continue;
            }

            if (Schema::hasColumn('roles', 'is_protected')) {
                DB::table('roles')
                    ->where('id', $roleId)
                    ->update(['is_protected' => true, 'updated_at' => now()]);
            }

            DB::table('permission_role')->where('role_id', $roleId)->delete();

            $rows = collect($allPermissionIds)
                ->map(fn (int $permissionId) => [
                    'role_id' => (int) $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($rows !== []) {
                DB::table('permission_role')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        // Fixed administrator role protection is intentionally not reversed.
    }
};
