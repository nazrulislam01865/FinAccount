<?php

namespace Tests\Feature;

use App\Models\AccountingOption;
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


    public function test_mixed_case_custom_transaction_type_can_be_selected_and_loads_legacy_case_heads(): void
    {
        [$user, $accounts] = $this->companyUserWithAccounts();

        AccountingOption::query()->create([
            'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
            'value' => 'Payment',
            'label' => 'Payment',
            'sort_order' => 15,
            'metadata' => [
                'voucher_prefix' => 'PAY',
                'money_label' => 'Pay From',
                'flow' => 'outgoing',
                'allowed_settlements' => TransactionTypes::ALL_SETTLEMENTS,
                'default_settlements' => [TransactionTypes::CASH],
            ],
            'is_active' => true,
        ]);

        $head = $this->head(
            $user->company_id,
            $accounts['expense']->id,
            'HPM',
            'Legacy Payment Head',
            'PAYMENT',
        );

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => 'Payment']))
            ->assertOk()
            ->assertSee('Record Payment Transaction')
            ->assertSee('name="category" value="Payment"', false)
            ->assertSee('Legacy Payment Head');

        $this->actingAs($user)
            ->get(route('transactions.create', ['category' => 'payment']))
            ->assertOk()
            ->assertSee('name="category" value="Payment"', false)
            ->assertSee('Legacy Payment Head');

        $this->actingAs($user)
            ->getJson(route('transactions.preview', [
                'category' => 'payment',
                'settlement_type' => TransactionTypes::CASH,
                'transaction_head_id' => $head->id,
                'amount' => 100,
                'paid_amount' => 100,
            ]))
            ->assertOk();
    }


    public function test_transaction_entry_filters_transaction_types_by_selected_direction(): void
    {
        [$user] = $this->companyUserWithAccounts();

        AccountingOption::query()->create([
            'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
            'value' => 'BANK_TRANSFER',
            'label' => 'Bank Transfer',
            'sort_order' => 200,
            'metadata' => [
                'voucher_prefix' => 'BTR',
                'money_label' => 'Transfer Through',
                'flow' => TransactionTypes::FLOW_TRANSFER,
                'allowed_settlements' => [TransactionTypes::CASH],
                'default_settlements' => [TransactionTypes::CASH],
            ],
            'is_active' => true,
        ]);
        AccountingOption::query()->create([
            'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
            'value' => 'JOURNAL_ADJUSTMENT',
            'label' => 'Journal Adjustment',
            'sort_order' => 210,
            'metadata' => [
                'voucher_prefix' => 'JAD',
                'flow' => TransactionTypes::FLOW_NON_CASH,
                'allowed_settlements' => [TransactionTypes::CASH],
                'default_settlements' => [TransactionTypes::CASH],
            ],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.create', ['direction' => TransactionTypes::FLOW_INCOMING]))
            ->assertOk()
            ->assertSee('Transaction Direction')
            ->assertSee('Money In')
            ->assertSee('Money Out')
            ->assertSee('Transfer')
            ->assertSee('Non-Cash')
            ->assertSee('data-category="SALE"', false)
            ->assertDontSee('data-category="PURCHASE"', false)
            ->assertDontSee('data-category="BANK_TRANSFER"', false);

        $this->actingAs($user)
            ->get(route('transactions.create', ['direction' => TransactionTypes::FLOW_OUTGOING]))
            ->assertOk()
            ->assertSee('data-category="PURCHASE"', false)
            ->assertSee('data-category="EXPENSE"', false)
            ->assertDontSee('data-category="SALE"', false)
            ->assertDontSee('data-category="BANK_TRANSFER"', false);

        $this->actingAs($user)
            ->get(route('transactions.create', ['direction' => TransactionTypes::FLOW_TRANSFER]))
            ->assertOk()
            ->assertSee('data-category="BANK_TRANSFER"', false)
            ->assertDontSee('data-category="SALE"', false)
            ->assertDontSee('data-category="PURCHASE"', false);

        $this->actingAs($user)
            ->get(route('transactions.create', ['direction' => TransactionTypes::FLOW_NON_CASH]))
            ->assertOk()
            ->assertSee('data-category="JOURNAL_ADJUSTMENT"', false)
            ->assertDontSee('data-category="SALE"', false)
            ->assertDontSee('data-category="PURCHASE"', false);
    }


    public function test_transaction_entry_initial_page_hides_only_type_buttons_not_the_form(): void
    {
        [$user] = $this->companyUserWithAccounts();

        $this->actingAs($user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('Transaction Direction')
            ->assertSee('Money In')
            ->assertSee('Money Out')
            ->assertSee('Transfer')
            ->assertSee('Non-Cash')
            ->assertDontSee('Transaction Type')
            ->assertDontSee('data-category="SALE"', false)
            ->assertSee('name="category" value="SALE"', false)
            ->assertSee('Transaction Head');

        $this->actingAs($user)
            ->get(route('transactions.create', ['direction' => TransactionTypes::FLOW_INCOMING]))
            ->assertOk()
            ->assertSee('Transaction Type')
            ->assertSee('data-category="SALE"', false)
            ->assertSee('name="category" value="SALE"', false);

        $this->actingAs($user)
            ->get(route('transactions.create', [
                'direction' => TransactionTypes::FLOW_INCOMING,
                'category' => TransactionTypes::SALE,
            ]))
            ->assertOk()
            ->assertSee('Record Sales Transaction')
            ->assertSee('name="category" value="SALE"', false);
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
