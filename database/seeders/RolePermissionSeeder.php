<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'company.view' => 'View Company Setup', 'company.manage' => 'Manage Company Setup',
            'chart-of-accounts.view' => 'View Chart of Accounts', 'chart-of-accounts.manage' => 'Manage Chart of Accounts',
            'cash-bank.view' => 'View Cash / Bank Setup', 'cash-bank.manage' => 'Manage Cash / Bank Setup',
            'parties.view' => 'View Parties', 'parties.manage' => 'Manage Parties',
            'transaction-heads.view' => 'View Transaction Heads', 'transaction-heads.manage' => 'Manage Transaction Heads',
            'master-data.view' => 'View Master Data', 'master-data.manage' => 'Manage Master Data',
            'users.view' => 'View Users', 'users.manage' => 'Manage Users',
            'opening-balances.view' => 'View Opening Balance Setup',
            'opening-balances.manage' => 'Manage Opening Balance Setup',
        ];

        $permissionModels = [];
        foreach ($permissions as $name => $label) {
            $permissionModels[$name] = Permission::updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $admin = Role::updateOrCreate(['name' => 'Admin'], ['description' => 'Full access', 'status' => 'Active']);
        $accountant = Role::updateOrCreate(['name' => 'Accountant'], ['description' => 'Accounting setup and review access', 'status' => 'Active']);
        $dataEntry = Role::updateOrCreate(['name' => 'Data Entry'], ['description' => 'Daily entry user', 'status' => 'Active']);
        $manager = Role::updateOrCreate(['name' => 'Manager'], ['description' => 'Read-only manager access', 'status' => 'Active']);

        $admin->permissions()->sync(collect($permissionModels)->pluck('id'));
        $accountant->permissions()->sync(collect($permissionModels)->only([
            'chart-of-accounts.view', 'chart-of-accounts.manage', 'cash-bank.view', 'cash-bank.manage',
            'parties.view', 'parties.manage', 'transaction-heads.view'
        ])->pluck('id'));
        $dataEntry->permissions()->sync(collect($permissionModels)->only(['parties.view'])->pluck('id'));
        $manager->permissions()->sync(collect($permissionModels)->only([
            'company.view', 'chart-of-accounts.view', 'cash-bank.view', 'parties.view'
        ])->pluck('id'));
    }
}
