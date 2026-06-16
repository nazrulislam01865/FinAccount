<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Accounting\BasicStatementService;
use App\Services\Accounting\PartyService;
use App\Services\Dashboard\DashboardService;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingTemplateParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HisebGhorDemoSeeder::class);
        $this->user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
    }

    public function test_template_master_and_transaction_dataset_is_seeded_exactly(): void
    {
        $companyId = $this->user->company_id;

        $this->assertSame([
            ['1111', 'Cash in Hand', 'Asset', 'Debit'],
            ['1112', 'BRAC Bank Current Account', 'Asset', 'Debit'],
            ['1113', 'bKash Business Account', 'Asset', 'Debit'],
            ['1121', 'Customer Receivable', 'Asset', 'Debit'],
            ['1211', 'Farm Materials / Feed Stock', 'Asset', 'Debit'],
            ['2111', 'Supplier Payable', 'Liability', 'Credit'],
            ['2211', 'Loan from Bank / Lender', 'Liability', 'Credit'],
            ['3111', 'Owner Capital', 'Equity', 'Credit'],
            ['4111', 'Farm Product Sales Income', 'Income', 'Credit'],
            ['4199', 'Other Operating Income', 'Income', 'Credit'],
            ['5111', 'Farm Worker Salary Expense', 'Expense', 'Debit'],
            ['5121', 'Cow/Fish Feed Expense', 'Expense', 'Debit'],
            ['5131', 'Internet & Mobile Bill Expense', 'Expense', 'Debit'],
            ['5141', 'Stationery Expense', 'Expense', 'Debit'],
            ['5211', 'Loan Interest Expense', 'Expense', 'Debit'],
        ], ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get()
            ->map(fn (ChartOfAccount $account): array => [
                $account->code,
                $account->name,
                $account->type,
                $account->normal_balance,
            ])->all());

        $this->assertSame([
            ['BRAC Bank - Farm Account', 'Bank', '1112', 25000.0],
            ['Main Cash Box', 'Cash', '1111', 5000.0],
            ['bKash Merchant Wallet', 'Digital', '1113', 3000.0],
        ], MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (MoneyAccount $account): array => [
                $account->name,
                $account->kind,
                $account->chartOfAccount->code,
                (float) $account->opening_balance,
            ])->all());

        $this->assertSame([
            ['C-001', 'Rahim Traders', 'Customer', '1121', null, 0.0],
            ['C-002', 'Local Vegetable Shop', 'Customer', '1121', null, 0.0],
            ['L-001', 'Agrani Bank Loan', 'Lender', null, '2211', 0.0],
            ['O-001', 'Business Owner', 'Owner', null, '3111', 0.0],
            ['S-001', 'Molla Feed Supplier', 'Supplier', null, '2111', 0.0],
            ['W-001', 'Farm Worker Group', 'Worker', null, null, 0.0],
        ], Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get()
            ->map(fn (Party $party): array => [
                $party->code,
                $party->name,
                $party->type,
                $party->receivableAccount?->code,
                $party->payableAccount?->code,
                (float) $party->opening_balance,
            ])->all());

        $this->assertSame([
            ['R-LIA-01', 'Liability', 'head_account', 'party_payable', true, 'Supplier', false],
            ['R-LIA-02', 'Liability', 'selected_money', 'party_payable', true, 'Lender', true],
            ['R-LIA-03', 'Liability', 'party_payable', 'selected_money', true, 'Lender', true],
            ['R-PAY-01', 'Payment', 'head_account', 'selected_money', false, 'Any', true],
            ['R-PAY-02', 'Payment', 'party_payable', 'selected_money', true, 'Supplier', true],
            ['R-SAL-01', 'Sales', 'selected_money', 'head_account', false, 'Any', true],
            ['R-SAL-02', 'Sales', 'party_receivable', 'head_account', true, 'Customer', false],
        ], AccountingRule::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get()
            ->map(fn (AccountingRule $rule): array => [
                $rule->code,
                $rule->category,
                $rule->debit_source,
                $rule->credit_source,
                $rule->party_required,
                $rule->party_type,
                $rule->money_required,
            ])->all());

        $this->assertSame([
            ['TH-L-001', 'Feed Purchase on Credit', 'Liability', 'R-LIA-01', '5121'],
            ['TH-L-002', 'Loan Received from Bank/Lender', 'Liability', 'R-LIA-02', '2211'],
            ['TH-L-003', 'Loan Principal Repayment', 'Liability', 'R-LIA-03', '2211'],
            ['TH-P-001', 'Farm Worker Salary Payment', 'Payment', 'R-PAY-01', '5111'],
            ['TH-P-002', 'Internet & Mobile Bill Payment', 'Payment', 'R-PAY-01', '5131'],
            ['TH-P-003', 'Supplier Due Payment', 'Payment', 'R-PAY-02', '2111'],
            ['TH-S-001', 'Milk Sale - Immediate Payment', 'Sales', 'R-SAL-01', '4111'],
            ['TH-S-002', 'Fish Sale - Immediate Payment', 'Sales', 'R-SAL-01', '4111'],
            ['TH-S-003', 'Vegetable Sale - Credit', 'Sales', 'R-SAL-02', '4111'],
        ], TransactionHead::query()
            ->with(['accountingRule', 'postingAccount'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get()
            ->map(fn (TransactionHead $head): array => [
                $head->code,
                $head->name,
                $head->category,
                $head->accountingRule->code,
                $head->postingAccount->code,
            ])->all());

        $this->assertSame([
            ['SAL-0001', 'Sales', 'TH-S-001', 'Main Cash Box', null, 2500.0, 'INV-101', 'Cow milk sold in cash'],
            ['SAL-0002', 'Sales', 'TH-S-003', null, 'C-002', 4200.0, 'INV-102', 'Vegetables sold on credit'],
            ['PAY-0001', 'Payment', 'TH-P-001', 'Main Cash Box', 'W-001', 3000.0, 'PAY-201', 'Farm worker salary paid'],
            ['LIA-0001', 'Liability', 'TH-L-001', null, 'S-001', 8000.0, 'BILL-301', 'Fish and cow feed purchased on credit'],
            ['PAY-0002', 'Payment', 'TH-P-003', 'BRAC Bank - Farm Account', 'S-001', 3000.0, 'PAY-302', 'Partial supplier due paid'],
            ['LIA-0002', 'Liability', 'TH-L-002', 'BRAC Bank - Farm Account', 'L-001', 50000.0, 'LOAN-01', 'Loan received in bank account'],
            ['LIA-0003', 'Liability', 'TH-L-003', 'BRAC Bank - Farm Account', 'L-001', 5000.0, 'LOAN-PAY-01', 'Loan principal repaid'],
        ], Transaction::query()
            ->with(['transactionHead', 'moneyAccount', 'party'])
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->map(fn (Transaction $transaction): array => [
                $transaction->voucher_no,
                $transaction->category,
                $transaction->transactionHead->code,
                $transaction->moneyAccount?->name,
                $transaction->party?->code,
                (float) $transaction->amount,
                $transaction->reference,
                $transaction->description,
            ])->all());
    }

    public function test_all_template_dropdown_domains_are_stored_in_the_database(): void
    {
        $expected = [
            AccountingOption::GROUP_ACCOUNT_TYPE => ['Asset', 'Liability', 'Income', 'Expense', 'Equity'],
            AccountingOption::GROUP_NORMAL_BALANCE => ['Debit', 'Credit'],
            AccountingOption::GROUP_MONEY_ACCOUNT_KIND => ['Cash', 'Bank', 'Digital'],
            AccountingOption::GROUP_PARTY_TYPE => ['Customer', 'Supplier', 'Worker', 'Owner', 'Lender'],
            AccountingOption::GROUP_RULE_PARTY_TYPE => ['Any', 'Customer', 'Supplier', 'Worker', 'Owner', 'Lender'],
            AccountingOption::GROUP_TRANSACTION_CATEGORY => ['Sales', 'Payment', 'Liability'],
            AccountingOption::GROUP_ACCOUNTING_SOURCE => ['selected_money', 'head_account', 'party_receivable', 'party_payable'],
        ];

        foreach ($expected as $group => $values) {
            $this->assertSame(
                $values,
                AccountingOption::query()
                    ->where('option_group', $group)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->pluck('value')
                    ->all(),
            );
        }
    }

    public function test_setup_pages_render_dropdown_values_from_database_records(): void
    {
        AccountingOption::query()->create([
            'option_group' => AccountingOption::GROUP_MONEY_ACCOUNT_KIND,
            'value' => 'Cheque',
            'label' => 'Cheque Account',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('money-accounts.index'))
            ->assertOk()
            ->assertSee('Cheque Account');

        $account = ChartOfAccount::query()->create([
            'company_id' => $this->user->company_id,
            'code' => '1199',
            'name' => 'Cheque Clearing Account',
            'type' => 'Asset',
            'normal_balance' => 'Debit',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('money-accounts.store'), [
                'name' => 'Cheque Desk',
                'kind' => 'Cheque',
                'chart_of_account_id' => $account->id,
                'opening_balance' => '0.00',
                'is_active' => 1,
            ])
            ->assertRedirect(route('money-accounts.index'));

        $this->assertDatabaseHas('money_accounts', [
            'company_id' => $this->user->company_id,
            'name' => 'Cheque Desk',
            'kind' => 'Cheque',
        ]);
    }

    public function test_sample_template_transactions_create_the_exact_journal_pairs(): void
    {
        $expected = [
            'SAL-0001' => [['1111', 2500.00, 0.00], ['4111', 0.00, 2500.00]],
            'SAL-0002' => [['1121', 4200.00, 0.00], ['4111', 0.00, 4200.00]],
            'PAY-0001' => [['5111', 3000.00, 0.00], ['1111', 0.00, 3000.00]],
            'LIA-0001' => [['5121', 8000.00, 0.00], ['2111', 0.00, 8000.00]],
            'PAY-0002' => [['2111', 3000.00, 0.00], ['1112', 0.00, 3000.00]],
            'LIA-0002' => [['1112', 50000.00, 0.00], ['2211', 0.00, 50000.00]],
            'LIA-0003' => [['2211', 5000.00, 0.00], ['1112', 0.00, 5000.00]],
        ];

        foreach ($expected as $voucher => $pairs) {
            $transaction = Transaction::query()->where('voucher_no', $voucher)->firstOrFail();
            $lines = JournalLine::query()
                ->with('chartOfAccount')
                ->where('journal_entry_id', $transaction->journalEntry()->value('id'))
                ->orderBy('sequence')
                ->get();

            $this->assertCount(2, $lines);

            foreach ($pairs as $index => [$code, $debit, $credit]) {
                $this->assertSame($code, $lines[$index]->chartOfAccount->code);
                $this->assertSame($debit, (float) $lines[$index]->debit);
                $this->assertSame($credit, (float) $lines[$index]->credit);
            }
        }
    }

    public function test_optional_party_is_retained_without_affecting_party_balance(): void
    {
        $salary = Transaction::query()->where('voucher_no', 'PAY-0001')->firstOrFail();
        $worker = Party::query()->where('code', 'W-001')->firstOrFail();

        $this->assertSame($worker->id, $salary->party_id);

        $balances = app(PartyService::class)->balancesFor(collect([$worker]), $this->user->company_id);
        $this->assertSame(0.0, $balances[$worker->id]);
    }

    public function test_template_dashboard_and_statement_totals_match_the_posted_journals(): void
    {
        $statement = app(BasicStatementService::class)->summary($this->user->company_id);
        $dashboard = app(DashboardService::class)->summary($this->user->company_id);

        $this->assertSame(6700.0, $dashboard['metrics']['sales']);
        $this->assertSame(6000.0, $dashboard['metrics']['payments']);
        $this->assertSame(63000.0, $dashboard['metrics']['liability']);
        $this->assertSame(74500.0, $dashboard['metrics']['money_balance']);

        $this->assertSame(6700.0, $statement['income']);
        $this->assertSame(11000.0, $statement['expense']);
        $this->assertSame(-4300.0, $statement['net']);
        $this->assertSame(78700.0, $statement['asset']);
        $this->assertSame(50000.0, $statement['liability']);
        $this->assertSame(-4300.0, $statement['equity_with_profit']);
        $this->assertSame(74500.0, $statement['cash']);
        $this->assertSame(2500.0, $statement['sales_collected']);
        $this->assertSame(11000.0, $statement['payments_made']);
    }

    public function test_category_and_head_category_must_match(): void
    {
        $paymentHead = TransactionHead::query()->where('code', 'TH-P-001')->firstOrFail();
        $cash = MoneyAccount::query()->where('name', 'Main Cash Box')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'category' => 'Sales',
                'transaction_date' => now()->toDateString(),
                'transaction_head_id' => $paymentHead->id,
                'money_account_id' => $cash->id,
                'amount' => '10.00',
                'request_token' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('transaction_head_id');
    }

    public function test_required_party_type_and_mapping_are_enforced(): void
    {
        $creditSaleHead = TransactionHead::query()->where('code', 'TH-S-003')->firstOrFail();
        $supplier = Party::query()->where('code', 'S-001')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'category' => 'Sales',
                'transaction_date' => now()->toDateString(),
                'transaction_head_id' => $creditSaleHead->id,
                'party_id' => $supplier->id,
                'amount' => '10.00',
                'request_token' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('party_id');
    }

    public function test_edit_rebuilds_the_two_journal_lines_and_delete_removes_them(): void
    {
        $transaction = Transaction::query()->where('voucher_no', 'SAL-0001')->firstOrFail();
        $head = TransactionHead::query()->where('code', 'TH-S-001')->firstOrFail();
        $bank = MoneyAccount::query()->where('name', 'BRAC Bank - Farm Account')->firstOrFail();

        $this->actingAs($this->user)
            ->put(route('transactions.update', $transaction), [
                'category' => 'Sales',
                'transaction_date' => now()->toDateString(),
                'transaction_head_id' => $head->id,
                'money_account_id' => $bank->id,
                'amount' => '999.00',
                'reference' => 'UPDATED',
            ])
            ->assertRedirect(route('transactions.index'));

        $transaction->refresh();
        $lines = $transaction->journalEntry()->firstOrFail()->lines()->with('chartOfAccount')->orderBy('sequence')->get();
        $this->assertSame('1112', $lines[0]->chartOfAccount->code);
        $this->assertSame(999.0, (float) $lines[0]->debit);
        $this->assertSame(999.0, (float) $lines[1]->credit);

        $journalId = $transaction->journalEntry()->value('id');

        $this->actingAs($this->user)
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseMissing('journal_entries', ['id' => $journalId]);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $journalId]);
    }

    public function test_journal_references_follow_rule_sources_instead_of_matching_account_ids(): void
    {
        $rule = AccountingRule::query()->where('code', 'R-PAY-01')->firstOrFail();
        $receivable = ChartOfAccount::query()->where('code', '1121')->firstOrFail();
        $customer = Party::query()->where('code', 'C-001')->firstOrFail();
        $cash = MoneyAccount::query()->where('name', 'Main Cash Box')->firstOrFail();

        $head = TransactionHead::query()->create([
            'company_id' => $this->user->company_id,
            'accounting_rule_id' => $rule->id,
            'posting_account_id' => $receivable->id,
            'code' => 'TH-P-099',
            'name' => 'Mapped Account Source Test',
            'category' => 'Payment',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'category' => 'Payment',
                'transaction_date' => now()->toDateString(),
                'transaction_head_id' => $head->id,
                'money_account_id' => $cash->id,
                'party_id' => $customer->id,
                'amount' => '25.00',
                'request_token' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('transactions.index'));

        $transaction = Transaction::query()->where('transaction_head_id', $head->id)->firstOrFail();
        $lines = $transaction->journalEntry()->firstOrFail()->lines()->orderBy('sequence')->get();

        $this->assertSame($customer->id, $transaction->party_id);
        $this->assertNull($lines[0]->party_id);
        $this->assertNull($lines[0]->money_account_id);
        $this->assertNull($lines[1]->party_id);
        $this->assertSame($cash->id, $lines[1]->money_account_id);

        $balances = app(PartyService::class)->balancesFor(collect([$customer]), $this->user->company_id);
        $this->assertSame(0.0, $balances[$customer->id]);
    }

    public function test_a_database_category_can_generate_its_own_voucher_sequence(): void
    {
        AccountingOption::query()->create([
            'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
            'value' => 'Adjustment',
            'label' => 'Adjustment',
            'sort_order' => 99,
            'metadata' => ['voucher_prefix' => 'ADJ', 'money_label' => 'Through'],
            'is_active' => true,
        ]);

        $rule = AccountingRule::query()->create([
            'company_id' => $this->user->company_id,
            'code' => 'R-ADJ-01',
            'name' => 'Adjustment Payment',
            'category' => 'Adjustment',
            'debit_source' => AccountingRule::SOURCE_HEAD_ACCOUNT,
            'credit_source' => AccountingRule::SOURCE_SELECTED_MONEY,
            'party_required' => false,
            'party_type' => 'Any',
            'money_required' => true,
            'is_active' => true,
        ]);
        $expense = ChartOfAccount::query()->where('code', '5141')->firstOrFail();
        $cash = MoneyAccount::query()->where('name', 'Main Cash Box')->firstOrFail();
        $head = TransactionHead::query()->create([
            'company_id' => $this->user->company_id,
            'accounting_rule_id' => $rule->id,
            'posting_account_id' => $expense->id,
            'code' => 'TH-A-001',
            'name' => 'Adjustment Entry',
            'category' => 'Adjustment',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'category' => 'Adjustment',
                'transaction_date' => now()->toDateString(),
                'transaction_head_id' => $head->id,
                'money_account_id' => $cash->id,
                'amount' => '50.00',
                'request_token' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->user->company_id,
            'category' => 'Adjustment',
            'voucher_no' => 'ADJ-0001',
        ]);
        $this->assertSame(2, (int) DocumentSequence::query()
            ->where('company_id', $this->user->company_id)
            ->where('category', 'Adjustment')
            ->value('next_number'));
    }


    public function test_sample_reset_is_company_scoped_and_restores_the_exact_dataset(): void
    {
        $otherCompany = Company::query()->create([
            'code' => 'OTHER',
            'name' => 'Other Company',
            'currency_code' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ]);
        $foreignAccount = ChartOfAccount::query()->create([
            'company_id' => $otherCompany->id,
            'code' => '9999',
            'name' => 'Foreign Account',
            'type' => 'Asset',
            'normal_balance' => 'Debit',
            'is_active' => true,
        ]);
        ChartOfAccount::query()->create([
            'company_id' => $this->user->company_id,
            'code' => '9998',
            'name' => 'Temporary Current Company Account',
            'type' => 'Asset',
            'normal_balance' => 'Debit',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('dashboard.reset-demo'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Sample data restored');

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $foreignAccount->id]);
        $this->assertDatabaseMissing('chart_of_accounts', [
            'company_id' => $this->user->company_id,
            'code' => '9998',
        ]);
        $this->assertSame(15, ChartOfAccount::query()->where('company_id', $this->user->company_id)->count());
        $this->assertSame(7, Transaction::query()->where('company_id', $this->user->company_id)->count());
        $this->assertSame(14, JournalLine::query()->where('company_id', $this->user->company_id)->count());
    }

    public function test_foreign_company_setup_cannot_be_used_or_opened(): void
    {
        $otherCompany = Company::query()->create([
            'code' => 'FOREIGN',
            'name' => 'Foreign Company',
            'currency_code' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ]);
        $foreignAccount = ChartOfAccount::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'F-100',
            'name' => 'Foreign Expense',
            'type' => 'Expense',
            'normal_balance' => 'Debit',
            'is_active' => true,
        ]);
        $foreignRule = AccountingRule::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'F-RULE',
            'name' => 'Foreign Rule',
            'category' => 'Payment',
            'debit_source' => AccountingRule::SOURCE_HEAD_ACCOUNT,
            'credit_source' => AccountingRule::SOURCE_SELECTED_MONEY,
            'party_required' => false,
            'party_type' => 'Any',
            'money_required' => true,
            'is_active' => true,
        ]);
        $foreignHead = TransactionHead::query()->create([
            'company_id' => $otherCompany->id,
            'accounting_rule_id' => $foreignRule->id,
            'posting_account_id' => $foreignAccount->id,
            'code' => 'F-HEAD',
            'name' => 'Foreign Head',
            'category' => 'Payment',
            'is_active' => true,
        ]);
        $foreignUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $foreignTransaction = Transaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $otherCompany->id,
            'transaction_head_id' => $foreignHead->id,
            'created_by' => $foreignUser->id,
            'voucher_no' => 'FOR-0001',
            'category' => 'Payment',
            'transaction_date' => now()->toDateString(),
            'amount' => '10.00',
            'request_token' => (string) Str::uuid(),
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        $cash = MoneyAccount::query()->where('company_id', $this->user->company_id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'category' => 'Payment',
                'transaction_date' => now()->toDateString(),
                'transaction_head_id' => $foreignHead->id,
                'money_account_id' => $cash->id,
                'amount' => '10.00',
                'request_token' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('transaction_head_id');

        $this->actingAs($this->user)
            ->get(route('transactions.edit', $foreignTransaction))
            ->assertNotFound();
    }

    public function test_used_setup_record_can_be_safely_deleted_after_dependency_confirmation(): void
    {
        $cashCoa = ChartOfAccount::query()->where('code', '1111')->firstOrFail();

        $this->actingAs($this->user)
            ->deleteJson(route('chart-of-accounts.destroy', $cashCoa), ['preview' => true])
            ->assertOk()
            ->assertJsonPath('plan.has_dependencies', true);

        $this->actingAs($this->user)
            ->deleteJson(route('chart-of-accounts.destroy', $cashCoa), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('chart_of_accounts', ['id' => $cashCoa->id]);
        $this->assertDatabaseHas('money_accounts', [
            'chart_of_account_id' => null,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('transactions', ['status' => 'incomplete']);
        $this->assertDatabaseHas('journal_entries', ['status' => 'incomplete']);
    }
}
