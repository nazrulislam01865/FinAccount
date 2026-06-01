<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('permissions')) {
            return;
        }

        if (!Schema::hasColumn('users', 'uses_direct_permissions')) {
            Schema::table('users', function (Blueprint $table) {
                $column = $table->boolean('uses_direct_permissions')->default(false);

                if (Schema::hasColumn('users', 'status')) {
                    $column->after('status');
                } elseif (Schema::hasColumn('users', 'password')) {
                    $column->after('password');
                }
            });
        }

        if (!Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['permission_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('role_user') || !Schema::hasTable('permission_role')) {
            return;
        }

        $allPermissionIds = DB::table('permissions')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fixedRoleIds = DB::table('roles')
            ->whereIn('name', Role::FIXED_FULL_ACCESS_ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $users = DB::table('users')->select('id')->get();

        foreach ($users as $user) {
            $roleIds = DB::table('role_user')
                ->where('user_id', (int) $user->id)
                ->pluck('role_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($roleIds === []) {
                DB::table('users')
                    ->where('id', (int) $user->id)
                    ->update(['uses_direct_permissions' => true, 'updated_at' => now()]);
                continue;
            }

            $hasFixedFullAccessRole = count(array_intersect($roleIds, $fixedRoleIds)) > 0;

            $permissionIds = $hasFixedFullAccessRole
                ? $allPermissionIds
                : DB::table('permission_role')
                    ->whereIn('role_id', $roleIds)
                    ->pluck('permission_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

            DB::table('permission_user')->where('user_id', (int) $user->id)->delete();

            $rows = collect($permissionIds)
                ->map(fn (int $permissionId) => [
                    'user_id' => (int) $user->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($rows !== []) {
                DB::table('permission_user')->insert($rows);
            }

            DB::table('users')
                ->where('id', (int) $user->id)
                ->update(['uses_direct_permissions' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'uses_direct_permissions')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('uses_direct_permissions');
            });
        }
    }
};
