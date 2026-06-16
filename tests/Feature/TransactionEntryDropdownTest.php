<?php

namespace Tests\Feature;

use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\MoneyAccount;
use App\Models\TransactionHead;
use App\Models\User;
use Database\Seeders\AccountingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionEntryDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_heads_are_loaded_from_the_table_and_filtered_by_category(): void
    {
        [$user, $accounts] = $this->companyUserWithAccounts();

        $salesRule = $this->rule($user->company_id, 'RS', 'Sales Rule', 'Sales');
        $paymentRule = $this->rule($user->company_id, 'RP', 'Payment Rule', 'Payment');
        $liabilityRule = $this->rule($user->company_id, 'RL', 'Liability Rule', 'Liability');

        $this->head($user->company_id, $salesRule->id, $accounts['income']->id, 'HS', 'Database Sales Head', 'Sales');
        $this->head($user->company_id, $paymentRule->id, $accounts['expense']->id, 'HP', 'Database Payment Head', 'Payment');
        $this->head($user->company_id, $liabilityRule->id, $accounts['liability']->id, 'HL', 'Database Liability Head', 'Liability');
        $this->head($user->company_id, $salesRule->id, $accounts['income']->id, 'HI', 'Inactive Sales Head', 'Sales', false);
        $this->head($user->company_id, $paymentRule->id, $accounts['income']->id, 'HM', 'Mismatched Sales Head', 'Sales');

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => 'Sales']))
            ->assertOk()
            ->assertSee('Database Sales Head')
            ->assertDontSee('Database Payment Head')
            ->assertDontSee('Database Liability Head')
            ->assertDontSee('Inactive Sales Head')
            ->assertDontSee('Mismatched Sales Head');

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => 'Payment']))
            ->assertOk()
            ->assertSee('Database Payment Head')
            ->assertDontSee('Database Sales Head')
            ->assertDontSee('Database Liability Head');

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => 'Liability']))
            ->assertOk()
            ->assertSee('Database Liability Head')
            ->assertDontSee('Database Sales Head')
            ->assertDontSee('Database Payment Head');
    }

    public function test_money_dropdown_is_loaded_from_active_money_accounts_with_active_coa(): void
    {
        [$user, $accounts] = $this->companyUserWithAccounts();

        MoneyAccount::query()->create([
            'company_id' => $user->company_id,
            'chart_of_account_id' => $accounts['cash']->id,
            'name' => 'Database Cash Account',
            'kind' => 'Cash',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        MoneyAccount::query()->create([
            'company_id' => $user->company_id,
            'chart_of_account_id' => $accounts['inactive_asset']->id,
            'name' => 'Inactive COA Money Account',
            'kind' => 'Bank',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => 'Sales']))
            ->assertOk()
            ->assertSee('Database Cash Account')
            ->assertDontSee('Inactive COA Money Account')
            ->assertDontSee('The user selects transaction category')
            ->assertDontSee('No debit/credit selection in transaction entry.')
            ->assertDontSee('Data saved securely in MySQL');
    }

    /** @return array{0: User, 1: array<string, ChartOfAccount>} */
    private function companyUserWithAccounts(): array
    {
        $this->seed(AccountingOptionSeeder::class);

        $company = Company::query()->create([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Company',
            'currency_code' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);

        $accounts = [
            'cash' => $this->account($company->id, '1001', 'Cash', 'Asset', 'Debit'),
            'income' => $this->account($company->id, '4001', 'Sales Income', 'Income', 'Credit'),
            'expense' => $this->account($company->id, '5001', 'Expense', 'Expense', 'Debit'),
            'liability' => $this->account($company->id, '2001', 'Liability', 'Liability', 'Credit'),
            'inactive_asset' => $this->account($company->id, '1099', 'Inactive Asset', 'Asset', 'Debit', false),
        ];

        return [$user, $accounts];
    }

    private function account(
        int $companyId,
        string $code,
        string $name,
        string $type,
        string $normalBalance,
        bool $active = true,
    ): ChartOfAccount {
        return ChartOfAccount::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => $active,
        ]);
    }

    private function rule(int $companyId, string $code, string $name, string $category): AccountingRule
    {
        return AccountingRule::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'debit_source' => 'selected_money',
            'credit_source' => 'head_account',
            'party_required' => false,
            'party_type' => 'Any',
            'money_required' => true,
            'is_active' => true,
        ]);
    }

    private function head(
        int $companyId,
        int $ruleId,
        int $postingAccountId,
        string $code,
        string $name,
        string $category,
        bool $active = true,
    ): TransactionHead {
        return TransactionHead::query()->create([
            'company_id' => $companyId,
            'accounting_rule_id' => $ruleId,
            'posting_account_id' => $postingAccountId,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'is_active' => $active,
        ]);
    }
}
