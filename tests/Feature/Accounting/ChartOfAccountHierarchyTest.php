<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\AccountingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_create_three_coa_levels_with_automatic_hierarchy_codes(): void
    {
        $user = $this->companyUser();

        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Assets', null, 'Asset'))
            ->assertSessionHasNoErrors();

        $levelOne = ChartOfAccount::query()->where('name', 'Assets')->sole();
        $this->assertSame(1, $levelOne->level);
        $this->assertNull($levelOne->parent_id);
        $this->assertSame('1000', $levelOne->code);

        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Current Assets', $levelOne->id, 'Expense'))
            ->assertSessionHasNoErrors();

        $levelTwo = ChartOfAccount::query()->where('name', 'Current Assets')->sole();
        $this->assertSame(2, $levelTwo->level);
        $this->assertSame($levelOne->id, $levelTwo->parent_id);
        $this->assertSame('1100', $levelTwo->code);
        $this->assertSame('Asset', $levelTwo->type, 'Child type must be inherited from its parent.');

        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Cash in Hand', $levelTwo->id, 'Liability'))
            ->assertSessionHasNoErrors();

        $levelThree = ChartOfAccount::query()->where('name', 'Cash in Hand')->sole();
        $this->assertSame(3, $levelThree->level);
        $this->assertSame($levelTwo->id, $levelThree->parent_id);
        $this->assertSame('1101', $levelThree->code);
        $this->assertSame('Asset', $levelThree->type);
    }

    public function test_each_level_uses_the_latest_previous_sibling_code(): void
    {
        $user = $this->companyUser();

        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Assets', null, 'Asset'))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Liabilities', null, 'Liability'))
            ->assertSessionHasNoErrors();

        $assets = ChartOfAccount::query()->where('name', 'Assets')->sole();
        $liabilities = ChartOfAccount::query()->where('name', 'Liabilities')->sole();
        $this->assertSame('1000', $assets->code);
        $this->assertSame('2000', $liabilities->code);

        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Current Assets', $assets->id, 'Expense'))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Fixed Assets', $assets->id, 'Income'))
            ->assertSessionHasNoErrors();

        $currentAssets = ChartOfAccount::query()->where('name', 'Current Assets')->sole();
        $fixedAssets = ChartOfAccount::query()->where('name', 'Fixed Assets')->sole();
        $this->assertSame('1100', $currentAssets->code);
        $this->assertSame('1200', $fixedAssets->code);

        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Cash in Hand', $currentAssets->id, 'Liability'))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Bank Account', $currentAssets->id, 'Equity'))
            ->assertSessionHasNoErrors();

        $this->assertSame('1101', ChartOfAccount::query()->where('name', 'Cash in Hand')->sole()->code);
        $this->assertSame('1102', ChartOfAccount::query()->where('name', 'Bank Account')->sole()->code);
    }

    public function test_level_three_account_cannot_be_selected_as_a_parent(): void
    {
        $user = $this->companyUser();
        $root = $this->account($user, '1000', 'Assets', 1);
        $category = $this->account($user, '1100', 'Current Assets', 2, $root->id);
        $ledger = $this->account($user, '1101', 'Cash in Hand', 3, $category->id);

        $this->actingAs($user)
            ->post(route('chart-of-accounts.store'), $this->payload('Invalid Level Four', $ledger->id, 'Asset'))
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('chart_of_accounts', ['name' => 'Invalid Level Four']);
    }

    public function test_coa_page_can_filter_and_display_accounts_by_level(): void
    {
        $user = $this->companyUser();
        $root = $this->account($user, '1000', 'Assets', 1);
        $this->account($user, '1100', 'Current Assets', 2, $root->id);
        $this->account($user, '2000', 'Legacy Posting Ledger', 3);

        $this->actingAs($user)
            ->get(route('chart-of-accounts.index', ['level' => 2]))
            ->assertOk()
            ->assertSee('Current Assets')
            ->assertDontSee('Legacy Posting Ledger')
            ->assertSee('Level 2 — Categories');
    }

    public function test_existing_unassigned_posting_ledger_stays_level_three_when_edited_without_a_parent(): void
    {
        $user = $this->companyUser();
        $ledger = $this->account($user, '1111', 'Existing Cash Ledger', 3);

        $this->actingAs($user)
            ->put(route('chart-of-accounts.update', $ledger), [
                'account_id' => $ledger->id,
                'parent_id' => null,
                'level' => 3,
                'code' => $ledger->code,
                'name' => 'Existing Cash Ledger Updated',
                'type' => 'Asset',
                'normal_balance' => 'Debit',
                'is_active' => '1',
                'coa_modal' => '1',
            ])
            ->assertSessionHasNoErrors();

        $ledger->refresh();
        $this->assertSame(3, $ledger->level);
        $this->assertNull($ledger->parent_id);
        $this->assertSame('1111', $ledger->code);
        $this->assertSame('Existing Cash Ledger Updated', $ledger->name);
    }

    public function test_only_level_three_accounts_are_available_as_transaction_posting_ledgers(): void
    {
        $user = $this->companyUser();
        $root = $this->account($user, '1000', 'Assets Main Group', 1);
        $category = $this->account($user, '1100', 'Current Assets Category', 2, $root->id);
        $this->account($user, '1101', 'Cash Posting Ledger', 3, $category->id);

        $this->actingAs($user)
            ->get(route('transaction-heads.index'))
            ->assertOk()
            ->assertSee('Cash Posting Ledger')
            ->assertDontSee('Assets Main Group')
            ->assertDontSee('Current Assets Category');
    }

    /** @return array<string, mixed> */
    private function payload(string $name, ?int $parentId, string $type): array
    {
        return [
            'parent_id' => $parentId,
            'level' => $parentId ? 2 : 1,
            'code' => '',
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type === 'Asset' || $type === 'Expense' ? 'Debit' : 'Credit',
            'is_active' => '1',
            'coa_modal' => '1',
        ];
    }

    private function account(
        User $user,
        string $code,
        string $name,
        int $level,
        ?int $parentId = null,
    ): ChartOfAccount {
        return ChartOfAccount::query()->create([
            'company_id' => $user->company_id,
            'parent_id' => $parentId,
            'level' => $level,
            'code' => $code,
            'name' => $name,
            'type' => 'Asset',
            'normal_balance' => 'Debit',
            'is_active' => true,
        ]);
    }

    private function companyUser(): User
    {
        $this->seed(AccountingOptionSeeder::class);
        $company = Company::query()->create([
            'code' => 'COA-'.uniqid(),
            'name' => 'COA Test Company',
            'currency_code' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ]);

        return User::factory()->create(['company_id' => $company->id]);
    }
}
