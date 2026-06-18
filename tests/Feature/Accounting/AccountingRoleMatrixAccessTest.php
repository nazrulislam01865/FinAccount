<?php

namespace Tests\Feature\Accounting;

use App\Models\Access\AccountingPermission;
use App\Models\Access\AccountingRole;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AccountingRbac;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingRoleMatrixAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HisebGhorDemoSeeder::class);
        $this->superAdmin = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
    }

    public function test_default_data_entry_role_can_work_with_transactions_and_reports_without_configuration_access(): void
    {
        $user = $this->createUserForRole('data_entry', 'entry@example.test');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('transactions.index'))->assertOk();
        $this->actingAs($user)->get(route('transactions.create'))->assertOk();
        $this->actingAs($user)->get(route('journal-entries.index'))->assertOk();
        $this->actingAs($user)->get(route('balances.index'))->assertOk();
        $this->actingAs($user)->get(route('basic-statements.index'))->assertOk();

        $this->actingAs($user)->get(route('chart-of-accounts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('system.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('system.settings.index'))->assertForbidden();
    }

    public function test_manage_only_permission_opens_add_screen_without_exposing_the_list(): void
    {
        $role = $this->createCustomRole('COA Creator', ['chart_of_accounts.manage']);
        $user = $this->createUser($role, 'coa-creator@example.test');

        $this->actingAs($user)
            ->get(route('chart-of-accounts.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('chart-of-accounts.index', ['action' => 'add']))
            ->assertOk()
            ->assertSee('You may add records, but your role is not allowed to view this list.')
            ->assertDontSee('Cash in Hand');

        $response = $this->actingAs($user)->post(route('chart-of-accounts.store'), [
            'code' => '5999',
            'name' => 'Manage Only Test Expense',
            'type' => 'Expense',
            'normal_balance' => 'Debit',
            'is_active' => 1,
        ]);

        $response
            ->assertRedirect(route('chart-of-accounts.index', ['action' => 'add']))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('chart_of_accounts', [
            'company_id' => $this->superAdmin->company_id,
            'code' => '5999',
        ]);
    }

    public function test_login_redirects_manage_only_user_to_the_first_allowed_add_screen(): void
    {
        $role = $this->createCustomRole('Manage Only Login', ['chart_of_accounts.manage']);
        $user = $this->createUser($role, 'manage-login@example.test');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('chart-of-accounts.index', ['action' => 'add']));
    }

    public function test_delete_records_is_independent_and_continues_to_use_safe_delete(): void
    {
        $role = $this->createCustomRole('Transaction Manager', ['transactions.view', 'transactions.manage']);
        $user = $this->createUser($role, 'transaction-manager@example.test');
        $transaction = Transaction::query()->where('company_id', $this->superAdmin->company_id)->firstOrFail();

        $this->actingAs($user)
            ->deleteJson(route('transactions.destroy', $transaction), ['preview' => true])
            ->assertForbidden();

        $this->allow($role, AccountingRbac::DELETE_PERMISSION_KEY);
        AccountingRbac::syncUserPermissionsFromRole($user->refresh());

        $this->actingAs($user)
            ->deleteJson(route('transactions.destroy', $transaction), ['preview' => true])
            ->assertOk()
            ->assertJsonPath('preview', true);
    }

    public function test_branding_settings_remain_super_admin_only(): void
    {
        $adminUser = $this->createUserForRole('admin_user', 'office-admin@example.test');

        $this->actingAs($this->superAdmin)
            ->get(route('system.settings.index'))
            ->assertOk()
            ->assertSee('Company Branding Settings')
            ->assertSee('System design, development, and intellectual property are owned by');

        $this->actingAs($adminUser)
            ->get(route('system.settings.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_change_logo_and_favicon(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superAdmin)
            ->post(route('system.settings.logo'), [
                'logo' => UploadedFile::fake()->image('company-logo.png', 420, 120),
            ])
            ->assertRedirect();

        $this->actingAs($this->superAdmin)
            ->post(route('system.settings.favicon'), [
                'favicon' => UploadedFile::fake()->image('company-icon.png', 64, 64),
            ])
            ->assertRedirect();

        $this->assertCount(1, Storage::disk('public')->files('companies/'.$this->superAdmin->company_id.'/branding/logo'));
        $this->assertCount(1, Storage::disk('public')->files('companies/'.$this->superAdmin->company_id.'/branding/favicon'));
    }

    public function test_permission_matrix_update_is_applied_to_assigned_users(): void
    {
        $role = $this->createCustomRole('Report Reader', []);
        $user = $this->createUser($role, 'report-reader@example.test');

        $this->actingAs($user)->get(route('basic-statements.index'))->assertForbidden();

        $permissions = AccountingPermission::query()->pluck('key')->all();
        $payload = ['permissions' => [$role->id => ['statements.view']]];
        foreach (AccountingRole::query()->where('company_id', $this->superAdmin->company_id)->get() as $existingRole) {
            if ($existingRole->id !== $role->id) {
                $payload['permissions'][$existingRole->id] = collect($permissions)
                    ->filter(fn (string $key): bool => (bool) DB::table('accounting_role_permissions')
                        ->where('role_id', $existingRole->id)
                        ->where('permission_id', AccountingPermission::query()->where('key', $key)->value('id'))
                        ->value('allowed'))
                    ->values()->all();
            }
        }

        $this->actingAs($this->superAdmin)
            ->post(route('system.role-matrix.update'), $payload)
            ->assertRedirect(route('system.role-matrix.index'));

        $this->actingAs($user->refresh())->get(route('basic-statements.index'))->assertOk();
    }

    private function createCustomRole(string $name, array $allowedKeys): AccountingRole
    {
        $role = AccountingRole::query()->create([
            'company_id' => $this->superAdmin->company_id,
            'name' => $name,
            'slug' => AccountingRbac::uniqueRoleSlug((int) $this->superAdmin->company_id, $name),
            'description' => 'Test role',
            'sort_order' => 500,
            'is_system' => false,
            'is_active' => true,
        ]);

        foreach (AccountingPermission::query()->get() as $permission) {
            DB::table('accounting_role_permissions')->insert([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'allowed' => in_array($permission->key, $allowedKeys, true),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $role;
    }

    private function createUserForRole(string $slug, string $email): User
    {
        $role = AccountingRole::query()
            ->where('company_id', $this->superAdmin->company_id)
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->createUser($role, $email);
    }

    private function createUser(AccountingRole $role, string $email): User
    {
        $user = User::query()->create([
            'company_id' => $this->superAdmin->company_id,
            'accounting_role_id' => $role->id,
            'role' => $role->isSuperAdmin() ? User::ROLE_SYSTEM_ADMIN : User::ROLE_ACCOUNTING_USER,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'name' => $role->name.' User',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
        AccountingRbac::syncUserPermissionsFromRole($user);

        return $user;
    }

    private function allow(AccountingRole $role, string $permissionKey): void
    {
        $permissionId = AccountingPermission::query()->where('key', $permissionKey)->value('id');
        DB::table('accounting_role_permissions')
            ->where('role_id', $role->id)
            ->where('permission_id', $permissionId)
            ->update(['allowed' => true, 'updated_at' => now()]);
    }
}
