<?php

namespace App\Support;

use App\Models\Access\AccountingPermission;
use App\Models\Access\AccountingRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AccountingRbac
{
    public const DELETE_PERMISSION_KEY = 'records.delete';

    public static function roles(): array
    {
        return [
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Full company-system owner with every permission and protected branding access.', 'sort_order' => 1, 'is_system' => true, 'is_active' => true],
            ['slug' => 'admin_user', 'name' => 'Admin User', 'description' => 'Office administrator who can manage accounting operations, setup, users and roles.', 'sort_order' => 2, 'is_system' => true, 'is_active' => true],
            ['slug' => 'accountant', 'name' => 'Accountant', 'description' => 'Can post transactions, maintain accounting setup and review all accounting reports.', 'sort_order' => 3, 'is_system' => true, 'is_active' => true],
            ['slug' => 'data_entry', 'name' => 'Data Entry User', 'description' => 'Can enter daily transactions and use supporting lists without changing system setup.', 'sort_order' => 4, 'is_system' => true, 'is_active' => true],
            ['slug' => 'viewer', 'name' => 'Viewer', 'description' => 'Read-only access to dashboards, registers, journals, balances and statements.', 'sort_order' => 5, 'is_system' => true, 'is_active' => true],
        ];
    }

    public static function permissions(): array
    {
        return [
            self::permission('dashboard.view', 'Dashboard', 'View', 'View Dashboard', 'Open the business health dashboard.', 'dashboard', 10),

            self::permission('transactions.view', 'Transactions', 'View', 'View Transaction Register', 'Open posted transactions and transaction details.', 'transactions.index', 20),
            self::permission('transactions.manage', 'Transactions', 'Manage', 'Manage Transactions', 'Create and update transactions. Deletion also requires Delete Records.', 'transactions.create', 21),
            self::permission('journals.view', 'Transactions', 'View', 'View Journal Entries', 'Open system-generated debit and credit journal lines.', 'journal-entries.index', 30),

            self::permission('balances.view', 'Reports', 'View', 'View Account & Party Balances', 'Open account balances and party balances.', 'balances.index', 40),
            self::permission('statements.view', 'Reports', 'View', 'View Financial Statements', 'Open the Income Statement, Balance Sheet and Cash Flow Statement.', 'basic-statements.index', 50),

            self::permission('company_setup.view', 'Configuration', 'View', 'View Company Setup', 'Open the company profile, accounting context, and contact setup.', 'company-setup.edit', 55),
            self::permission('company_setup.manage', 'Configuration', 'Manage', 'Manage Company Setup', 'Update company profile, accounting method, currency, time zone, and current financial year.', 'company-setup.update', 56),
            self::permission('business_types.view', 'Configuration', 'View', 'View Business Types', 'Open Business Type master data used by Company Setup.', 'master.business-types.index', 57),
            self::permission('business_types.manage', 'Configuration', 'Manage', 'Manage Business Types', 'Create and update Business Types. Deletion also requires Delete Records.', 'master.business-types.store', 58),
            self::permission('currencies.view', 'Configuration', 'View', 'View Currencies', 'Open Currency master data used across accounting screens.', 'master.currencies.index', 59),
            self::permission('currencies.manage', 'Configuration', 'Manage', 'Manage Currencies', 'Create and update Currencies. Deletion also requires Delete Records.', 'master.currencies.store', 60),
            self::permission('time_zones.view', 'Configuration', 'View', 'View Time Zones', 'Open Time Zone master data used by Company Setup.', 'master.time-zones.index', 61),
            self::permission('time_zones.manage', 'Configuration', 'Manage', 'Manage Time Zones', 'Create and update Time Zones. Deletion also requires Delete Records.', 'master.time-zones.store', 62),
            self::permission('financial_years.view', 'Configuration', 'View', 'View Financial Years', 'Open accounting Financial Year master data.', 'master.financial-years.index', 63),
            self::permission('financial_years.manage', 'Configuration', 'Manage', 'Manage Financial Years', 'Create, close, lock, and update Financial Years. Deletion also requires Delete Records.', 'master.financial-years.store', 64),

            self::permission('chart_of_accounts.view', 'Configuration', 'View', 'View Chart of Accounts', 'Open the company chart of accounts.', 'chart-of-accounts.index', 70),
            self::permission('chart_of_accounts.manage', 'Configuration', 'Manage', 'Manage Chart of Accounts', 'Create and update COA records. Deletion also requires Delete Records.', 'chart-of-accounts.store', 61),
            self::permission('opening_balances.view', 'Configuration', 'View', 'View Opening Balances', 'Open beginning balances by COA, party and money account.', 'opening-balances.index', 69),
            self::permission('opening_balances.manage', 'Configuration', 'Manage', 'Manage Opening Balances', 'Create and update separated opening balances. Deletion also requires Delete Records.', 'opening-balances.store', 69),
            self::permission('accounting_rules.view', 'Configuration', 'View', 'View Accounting Rules', 'Open accounting source rules.', 'accounting-rules.index', 70),
            self::permission('accounting_rules.manage', 'Configuration', 'Manage', 'Manage Accounting Rules', 'Create and update accounting rules. Deletion also requires Delete Records.', 'accounting-rules.store', 71),
            self::permission('transaction_heads.view', 'Configuration', 'View', 'View Transaction Heads', 'Open user-facing transaction heads.', 'transaction-heads.index', 80),
            self::permission('transaction_heads.manage', 'Configuration', 'Manage', 'Manage Transaction Heads', 'Create and update transaction heads. Deletion also requires Delete Records.', 'transaction-heads.store', 81),
            self::permission('transaction_categories.view', 'Configuration', 'View', 'View Transaction Types', 'Open transaction category master data.', 'master.index', 90),
            self::permission('transaction_categories.manage', 'Configuration', 'Manage', 'Manage Transaction Types', 'Create and update transaction categories. Deletion also requires Delete Records.', 'master.store', 91),
            self::permission('voucher_numbering.view', 'Configuration', 'View', 'View Voucher Numbering', 'Open voucher sequence configuration.', 'master.voucher-sequences.index', 100),
            self::permission('voucher_numbering.manage', 'Configuration', 'Manage', 'Manage Voucher Numbering', 'Create and update voucher sequences. Deletion also requires Delete Records.', 'master.voucher-sequences.store', 101),
            self::permission('party_types.view', 'Configuration', 'View', 'View Party Types', 'Open party-type master data.', null, 110),
            self::permission('party_types.manage', 'Configuration', 'Manage', 'Manage Party Types', 'Create and update party types. Deletion also requires Delete Records.', null, 111),
            self::permission('parties.view', 'Configuration', 'View', 'View Parties', 'Open customers, suppliers, workers, owners and lenders.', 'parties.index', 120),
            self::permission('parties.manage', 'Configuration', 'Manage', 'Manage Parties', 'Create and update parties. Deletion also requires Delete Records.', 'parties.store', 121),
            self::permission('money_account_types.view', 'Configuration', 'View', 'View Money Account Types', 'Open cash, bank and digital-account types.', null, 130),
            self::permission('money_account_types.manage', 'Configuration', 'Manage', 'Manage Money Account Types', 'Create and update money account types. Deletion also requires Delete Records.', null, 131),
            self::permission('money_accounts.view', 'Configuration', 'View', 'View Money Accounts', 'Open cash, bank and digital wallet accounts.', 'money-accounts.index', 140),
            self::permission('money_accounts.manage', 'Configuration', 'Manage', 'Manage Money Accounts', 'Create and update money accounts. Deletion also requires Delete Records.', 'money-accounts.store', 141),
            self::permission('master_data.view', 'Configuration', 'View', 'View Other Master Data', 'Open the configuration overview page.', 'master.overview', 150),

            self::permission('users.view', 'System', 'View', 'View Users', 'Open company user management.', 'system.users.index', 200),
            self::permission('users.manage', 'System', 'Manage', 'Manage Users', 'Create users, assign roles and update account status.', 'system.users.store', 201),
            self::permission('role_matrix.view', 'System', 'View', 'View Role Matrix', 'Open roles and their permission matrix.', 'system.role-matrix.index', 210),
            self::permission('role_matrix.manage', 'System', 'Manage', 'Manage Role Matrix', 'Create roles and update role permissions.', 'system.role-matrix.update', 211),
            self::permission(self::DELETE_PERMISSION_KEY, 'System', 'Delete', 'Delete Records', 'Use the existing safe-delete workflow for transactions and setup records.', null, 220),
            self::permission('settings.manage', 'System', 'Manage', 'Manage Branding Settings', 'Super Admin only: update the system logo and favicon.', 'system.settings.index', 230),
        ];
    }

    public static function defaultAllowedPermissions(): array
    {
        $allAccounting = collect(self::permissions())
            ->pluck('key')
            ->reject(fn (string $key): bool => in_array($key, [self::DELETE_PERMISSION_KEY, 'settings.manage'], true))
            ->values()->all();

        return [
            'admin_user' => $allAccounting,
            'accountant' => [
                'dashboard.view', 'transactions.view', 'transactions.manage', 'journals.view',
                'balances.view', 'statements.view', 'company_setup.view',
                'business_types.view', 'currencies.view', 'time_zones.view',
                'financial_years.view', 'financial_years.manage',
                'chart_of_accounts.view', 'chart_of_accounts.manage', 'opening_balances.view', 'opening_balances.manage',
                'accounting_rules.view', 'accounting_rules.manage', 'transaction_heads.view', 'transaction_heads.manage',
                'transaction_categories.view', 'transaction_categories.manage', 'voucher_numbering.view', 'voucher_numbering.manage',
                'party_types.view', 'party_types.manage', 'parties.view', 'parties.manage',
                'money_account_types.view', 'money_account_types.manage', 'money_accounts.view', 'money_accounts.manage',
                'master_data.view',
            ],
            'data_entry' => [
                'dashboard.view', 'transactions.view', 'transactions.manage', 'journals.view',
                'balances.view', 'statements.view',
                'transaction_heads.view', 'parties.view', 'money_accounts.view',
            ],
            'viewer' => ['dashboard.view', 'transactions.view', 'journals.view', 'balances.view', 'statements.view'],
        ];
    }

    public static function syncPermissions(): void
    {
        if (! Schema::hasTable('accounting_permissions')) {
            return;
        }

        $now = now();
        foreach (self::permissions() as $permission) {
            DB::table('accounting_permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [...$permission, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public static function syncCompany(int $companyId, bool $force = false): void
    {
        if (! Schema::hasTable('accounting_roles') || ! Schema::hasTable('accounting_permissions') || ! Schema::hasTable('accounting_role_permissions')) {
            return;
        }

        self::syncPermissions();
        $now = now();

        foreach (self::roles() as $role) {
            DB::table('accounting_roles')->updateOrInsert(
                ['company_id' => $companyId, 'slug' => $role['slug']],
                [...$role, 'company_id' => $companyId, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $roles = DB::table('accounting_roles')->where('company_id', $companyId)->pluck('id', 'slug');
        $permissions = DB::table('accounting_permissions')->pluck('id', 'key');
        $defaults = self::defaultAllowedPermissions();

        foreach ($roles as $slug => $roleId) {
            if (! in_array($slug, array_column(self::roles(), 'slug'), true)) {
                continue;
            }
            foreach ($permissions as $key => $permissionId) {
                $allowed = $slug === 'super_admin' || in_array($key, $defaults[$slug] ?? [], true);
                if ($key === 'settings.manage') {
                    $allowed = $slug === 'super_admin';
                }
                $exists = DB::table('accounting_role_permissions')
                    ->where('role_id', $roleId)->where('permission_id', $permissionId)->exists();
                if ($force || ! $exists || $slug === 'super_admin' || $key === 'settings.manage') {
                    DB::table('accounting_role_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $permissionId],
                        ['allowed' => $allowed, 'created_at' => $now, 'updated_at' => $now],
                    );
                }
            }
        }
    }

    public static function syncAllCompanies(bool $force = false): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }
        self::syncPermissions();
        Company::query()->pluck('id')->each(fn ($id) => self::syncCompany((int) $id, $force));
    }

    public static function assignExistingUsers(): void
    {
        if (! Schema::hasColumn('users', 'accounting_role_id') || ! Schema::hasTable('accounting_roles')) {
            return;
        }

        User::query()->whereNull('accounting_role_id')->orderBy('id')->each(function (User $user): void {
            if (! $user->company_id) {
                return;
            }
            self::syncCompany((int) $user->company_id);
            $slug = $user->role === User::ROLE_SYSTEM_ADMIN ? 'super_admin' : 'data_entry';
            $roleId = DB::table('accounting_roles')
                ->where('company_id', $user->company_id)->where('slug', $slug)->value('id');
            if ($roleId) {
                $user->forceFill(['accounting_role_id' => $roleId])->saveQuietly();
                self::syncUserPermissionsFromRole($user);
            }
        });
    }

    public static function syncUserPermissionsFromRole(User $user): void
    {
        if (! $user->accounting_role_id || ! Schema::hasTable('accounting_user_permissions')) {
            return;
        }
        $role = AccountingRole::query()->find($user->accounting_role_id);
        if (! $role || (int) $role->company_id !== (int) $user->company_id) {
            return;
        }
        $allowed = DB::table('accounting_role_permissions')->where('role_id', $role->id)->pluck('allowed', 'permission_id');
        $now = now();
        AccountingPermission::query()->pluck('id')->each(function ($permissionId) use ($user, $role, $allowed, $now): void {
            DB::table('accounting_user_permissions')->updateOrInsert(
                ['user_id' => $user->id, 'permission_id' => $permissionId],
                ['allowed' => $role->isSuperAdmin() || (bool) ($allowed[$permissionId] ?? false), 'created_at' => $now, 'updated_at' => $now],
            );
        });
        $user->forgetAccountingPermissionMap();
    }

    public static function uniqueRoleSlug(int $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $suffix = 2;
        while (AccountingRole::query()->where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }

    public static function pairedPermission(string $permissionKey, string $action): ?string
    {
        $suffix = '.'.strtolower(trim($action));
        $base = preg_replace('/\.(view|manage)$/', '', $permissionKey) ?: $permissionKey;
        $candidate = $base.$suffix;

        return collect(self::permissions())->contains(fn (array $permission): bool => $permission['key'] === $candidate)
            ? $candidate
            : null;
    }

    /** @return array{route: string, parameters: array<string, mixed>} */
    public static function firstAllowedDestination(?User $user): array
    {
        $candidates = [
            ['dashboard.view', 'dashboard', []],
            ['transactions.view', 'transactions.index', []],
            ['transactions.manage', 'transactions.create', []],
            ['journals.view', 'journal-entries.index', []],
            ['balances.view', 'balances.index', []],
            ['statements.view', 'basic-statements.index', []],
            ['company_setup.view', 'company-setup.edit', []],
            ['company_setup.manage', 'company-setup.edit', []],
            ['business_types.view', 'master.business-types.index', []],
            ['business_types.manage', 'master.business-types.index', ['action' => 'add']],
            ['currencies.view', 'master.currencies.index', []],
            ['currencies.manage', 'master.currencies.index', ['action' => 'add']],
            ['time_zones.view', 'master.time-zones.index', []],
            ['time_zones.manage', 'master.time-zones.index', ['action' => 'add']],
            ['financial_years.view', 'master.financial-years.index', []],
            ['financial_years.manage', 'master.financial-years.index', ['action' => 'add']],
            ['chart_of_accounts.view', 'chart-of-accounts.index', []],
            ['chart_of_accounts.manage', 'chart-of-accounts.index', ['action' => 'add']],
            ['opening_balances.view', 'opening-balances.index', []],
            ['opening_balances.manage', 'opening-balances.index', ['action' => 'add']],
            ['accounting_rules.view', 'accounting-rules.index', []],
            ['accounting_rules.manage', 'accounting-rules.index', ['action' => 'add']],
            ['transaction_heads.view', 'transaction-heads.index', []],
            ['transaction_heads.manage', 'transaction-heads.index', ['action' => 'add']],
            ['transaction_categories.view', 'master.index', ['section' => 'transaction-categories']],
            ['transaction_categories.manage', 'master.index', ['section' => 'transaction-categories', 'action' => 'add']],
            ['voucher_numbering.view', 'master.voucher-sequences.index', []],
            ['voucher_numbering.manage', 'master.voucher-sequences.index', ['action' => 'add']],
            ['party_types.view', 'master.index', ['section' => 'party-types']],
            ['party_types.manage', 'master.index', ['section' => 'party-types', 'action' => 'add']],
            ['parties.view', 'parties.index', []],
            ['parties.manage', 'parties.index', ['action' => 'add']],
            ['money_account_types.view', 'master.index', ['section' => 'money-account-types']],
            ['money_account_types.manage', 'master.index', ['section' => 'money-account-types', 'action' => 'add']],
            ['money_accounts.view', 'money-accounts.index', []],
            ['money_accounts.manage', 'money-accounts.index', ['action' => 'add']],
            ['master_data.view', 'master.overview', []],
            ['users.view', 'system.users.index', []],
            ['users.manage', 'system.users.index', ['action' => 'add']],
            ['role_matrix.view', 'system.role-matrix.index', []],
            ['role_matrix.manage', 'system.role-matrix.index', ['action' => 'add']],
            ['settings.manage', 'system.settings.index', []],
        ];

        foreach ($candidates as [$permission, $route, $parameters]) {
            if ($user?->canAccounting($permission)) {
                return ['route' => $route, 'parameters' => $parameters];
            }
        }

        return ['route' => 'dashboard', 'parameters' => []];
    }

    public static function firstAllowedRoute(?User $user): string
    {
        return self::firstAllowedDestination($user)['route'];
    }

    private static function permission(string $key, string $module, string $action, string $label, string $description, ?string $routeName, int $sortOrder): array
    {
        return [
            'key' => $key,
            'module' => $module,
            'action' => $action,
            'label' => $label,
            'description' => $description,
            'route_name' => $routeName,
            'sort_order' => $sortOrder,
        ];
    }
}
