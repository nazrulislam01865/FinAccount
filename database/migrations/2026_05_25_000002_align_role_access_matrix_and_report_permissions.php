<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $permissionDefinitions = config('access.permissions', []);

        foreach ($permissionDefinitions as $name => $label) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['label' => $label, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $allPermissionIds = $permissionIds->values()->map(fn ($id) => (int) $id)->all();

        $this->syncRolePermissions('Super Admin', $allPermissionIds);
        $this->syncRolePermissions('Admin', $allPermissionIds);
        $this->syncRolePermissions('Company Admin', $allPermissionIds);

        $this->grantRolePermissions('Accountant', [
            'company.view',
            'master-data.view',
            'master-data.manage',
            'transaction-heads.manage',
            'ledger-mapping.manage',
            'voucher-numbering.manage',
            'reports.full',
        ], $permissionIds);

        $this->revokeRolePermissions('Management Viewer / Report Viewer', [
            'reports.full',
        ], $permissionIds);

        $this->revokeRolePermissions('Data Entry Operator', [
            'transactions.create',
            'transactions.edit',
            'transactions.payment.create',
            'transactions.receipt.create',
            'transactions.journal.create',
            'transactions.sales.create',
            'transactions.purchase.create',
            'transactions.reverse',
            'reports.full',
            'ledger-mapping.manage',
            'transaction-heads.manage',
            'opening-balances.manage',
        ], $permissionIds);
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        $this->revokeRolePermissions('Accountant', [
            'company.view',
            'master-data.manage',
            'transaction-heads.manage',
            'ledger-mapping.manage',
            'voucher-numbering.manage',
            'reports.full',
        ], $permissionIds);

        $this->grantRolePermissions('Management Viewer / Report Viewer', [
            'reports.full',
        ], $permissionIds);
    }

    /**
     * @param array<int, int> $permissionIds
     */
    private function syncRolePermissions(string $roleName, array $permissionIds): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if (!$roleId) {
            return;
        }

        DB::table('permission_role')->where('role_id', $roleId)->delete();

        $rows = collect($permissionIds)
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

    private function grantRolePermissions(string $roleName, array $permissions, $permissionIds): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if (!$roleId) {
            return;
        }

        foreach ($permissions as $permission) {
            $permissionId = $permissionIds[$permission] ?? null;

            if (!$permissionId) {
                continue;
            }

            DB::table('permission_role')->updateOrInsert(
                ['role_id' => (int) $roleId, 'permission_id' => (int) $permissionId],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function revokeRolePermissions(string $roleName, array $permissions, $permissionIds): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if (!$roleId) {
            return;
        }

        $ids = collect($permissions)
            ->map(fn (string $permission) => $permissionIds[$permission] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($ids !== []) {
            DB::table('permission_role')
                ->where('role_id', (int) $roleId)
                ->whereIn('permission_id', $ids)
                ->delete();
        }
    }
};
