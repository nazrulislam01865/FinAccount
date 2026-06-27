<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\MoneyAccount;
use App\Models\TransactionHead;
use App\Models\User;
use App\Support\TransactionTypes;
use Database\Seeders\AccountingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionEntryDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_heads_are_filtered_by_transaction_type_and_account_nature(): void
    {
        [$user, $accounts] = $this->companyUserWithAccounts();

        $this->head($user->company_id, $accounts['income']->id, 'HS', 'Database Sale Head', TransactionTypes::SALE);
        $this->head($user->company_id, $accounts['expense']->id, 'HE', 'Database Expense Head', TransactionTypes::EXPENSE);
        $this->head($user->company_id, $accounts['expense']->id, 'HP', 'Database Purchase Head', TransactionTypes::PURCHASE);
        $this->head($user->company_id, $accounts['income']->id, 'HI', 'Inactive Sale Head', TransactionTypes::SALE, false);
        $this->head($user->company_id, $accounts['expense']->id, 'HM', 'Wrong Sale Account', TransactionTypes::SALE);

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => TransactionTypes::SALE]))
            ->assertOk()
            ->assertSee('Database Sale Head')
            ->assertDontSee('Database Expense Head')
            ->assertDontSee('Inactive Sale Head')
            ->assertDontSee('Wrong Sale Account')
            ->assertSee('How was payment handled?');

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => TransactionTypes::EXPENSE]))
            ->assertOk()
            ->assertSee('Database Expense Head')
            ->assertDontSee('Database Sale Head');
    }

    public function test_money_dropdown_only_uses_active_money_accounts_with_active_coa(): void
    {
        [$user, $accounts] = $this->companyUserWithAccounts();
        $this->head($user->company_id, $accounts['income']->id, 'HS', 'Database Sale Head', TransactionTypes::SALE);

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
            ->get(route('transactions.create', ['category' => TransactionTypes::SALE]))
            ->assertOk()
            ->assertSee('Database Cash Account')
            ->assertDontSee('Inactive COA Money Account');
    }

    private function companyUserWithAccounts(): array
    {
        $this->seed(AccountingOptionSeeder::class);
        $company = Company::query()->create([
            'code' => 'TEST-'.uniqid(), 'name' => 'Test Company', 'currency_code' => 'BDT', 'timezone' => 'Asia/Dhaka', 'status' => 'active',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        return [$user, [
            'cash' => $this->account($company->id, '1001', 'Cash', 'Asset', 'Debit'),
            'income' => $this->account($company->id, '4001', 'Sales Income', 'Income', 'Credit'),
            'expense' => $this->account($company->id, '5001', 'Expense', 'Expense', 'Debit'),
            'inactive_asset' => $this->account($company->id, '1099', 'Inactive Asset', 'Asset', 'Debit', false),
        ]];
    }

    private function account(int $companyId, string $code, string $name, string $type, string $normalBalance, bool $active = true): ChartOfAccount
    {
        return ChartOfAccount::query()->create(compact('code', 'name') + [
            'company_id' => $companyId, 'type' => $type, 'normal_balance' => $normalBalance, 'is_active' => $active,
        ]);
    }

    private function head(int $companyId, int $postingAccountId, string $code, string $name, string $category, bool $active = true): TransactionHead
    {
        return TransactionHead::query()->create([
            'company_id' => $companyId,
            'accounting_rule_id' => null,
            'posting_account_id' => $postingAccountId,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'allowed_settlements' => TransactionTypes::allowedSettlements($category),
            'party_type' => TransactionTypes::partyType($category),
            'is_active' => $active,
        ]);
    }
}
