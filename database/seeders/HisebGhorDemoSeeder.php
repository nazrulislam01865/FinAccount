<?php

namespace Database\Seeders;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\MoneyAccount;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Accounting\AccountingRuleService;
use App\Services\Accounting\TransactionPostingService;
use App\Services\Company\CompanySetupDefaultsService;
use App\Support\AccountingRbac;
use App\Support\TransactionTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HisebGhorDemoSeeder extends Seeder
{
    public function run(
        TransactionPostingService $postingService,
        CompanySetupDefaultsService $companySetupDefaults,
    ): void {
        $company = Company::query()->updateOrCreate(
            ['code' => 'HG-DEMO'],
            [
                'name' => 'HisebGhor Demo Company',
                'currency_code' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'status' => 'active',
            ],
        );

        $company = $companySetupDefaults->ensureForCompany($company);
        AccountingRbac::syncCompany((int) $company->id, true);

        $superAdminRoleId = \App\Models\Access\AccountingRole::query()
            ->where('company_id', $company->id)
            ->where('slug', 'super_admin')
            ->value('id');

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@hisebghor.test'],
            [
                'company_id' => $company->id,
                'accounting_role_id' => $superAdminRoleId,
                'role' => User::ROLE_SYSTEM_ADMIN,
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                'name' => 'HisebGhor Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );
        AccountingRbac::syncUserPermissionsFromRole($user);

        $this->seedCompany($company, $user, $postingService);
    }

    public function seedCompany(Company $company, User $user, TransactionPostingService $postingService): void
    {
        $company = app(CompanySetupDefaultsService::class)->ensureForCompany($company);
        $this->call(AccountingOptionSeeder::class);

        $accountDefinitions = [
            'cash' => ['1111', 'Cash in Hand', 'Asset', 'Debit'],
            'bank' => ['1112', 'BRAC Bank Current Account', 'Asset', 'Debit'],
            'bkash' => ['1113', 'bKash Business Account', 'Asset', 'Debit'],
            'receivable' => ['1121', 'Customer Receivable', 'Asset', 'Debit'],
            'feed_stock' => ['1211', 'Farm Materials / Feed Stock', 'Asset', 'Debit'],
            'vehicle_asset' => ['1511', 'Vehicle and Equipment', 'Asset', 'Debit'],
            'supplier_payable' => ['2111', 'Supplier Payable', 'Liability', 'Credit'],
            'salary_payable' => ['2121', 'Salary Payable', 'Liability', 'Credit'],
            'loan' => ['2211', 'Loan from Bank / Lender', 'Liability', 'Credit'],
            'capital' => ['3111', 'Owner Capital', 'Equity', 'Credit'],
            'drawing' => ['3121', 'Owner Drawing', 'Equity', 'Debit'],
            'sales' => ['4111', 'Farm Product Sales Income', 'Income', 'Credit'],
            'salary' => ['5111', 'Farm Worker Salary Expense', 'Expense', 'Debit'],
            'feed_expense' => ['5121', 'Cow/Fish Feed Expense', 'Expense', 'Debit'],
            'internet' => ['5131', 'Internet & Mobile Bill Expense', 'Expense', 'Debit'],
            'interest' => ['5211', 'Loan Interest Expense', 'Expense', 'Debit'],
        ];

        $accounts = [];
        foreach ($accountDefinitions as $key => [$code, $name, $type, $normalBalance]) {
            $accounts[$key] = ChartOfAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'normal_balance' => $normalBalance, 'is_active' => true],
            );
        }

        $money = [
            'cash' => MoneyAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Main Cash Box'],
                ['chart_of_account_id' => $accounts['cash']->id, 'kind' => 'Cash', 'is_active' => true],
            ),
            'bank' => MoneyAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'BRAC Bank - Farm Account'],
                ['chart_of_account_id' => $accounts['bank']->id, 'kind' => 'Bank', 'is_active' => true],
            ),
            'bkash' => MoneyAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'bKash Merchant Wallet'],
                ['chart_of_account_id' => $accounts['bkash']->id, 'kind' => 'Digital', 'is_active' => true],
            ),
        ];

        $openingYear = $company->defaultFinancialYear;
        $openingDate = $openingYear?->start_date?->toDateString() ?? now()->toDateString();
        foreach ([
            ['account' => $accounts['cash'], 'money' => $money['cash'], 'debit' => 5000.00, 'credit' => 0.00],
            ['account' => $accounts['bank'], 'money' => $money['bank'], 'debit' => 25000.00, 'credit' => 0.00],
            ['account' => $accounts['bkash'], 'money' => $money['bkash'], 'debit' => 3000.00, 'credit' => 0.00],
            ['account' => $accounts['capital'], 'money' => null, 'debit' => 0.00, 'credit' => 33000.00],
        ] as $openingRow) {
            OpeningBalance::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'financial_year_id' => $openingYear?->id,
                    'chart_of_account_id' => $openingRow['account']->id,
                    'money_account_id' => $openingRow['money']?->id,
                    'party_id' => null,
                    'reference' => 'DEMO-OPENING',
                ],
                [
                    'balance_date' => $openingDate,
                    'debit' => $openingRow['debit'],
                    'credit' => $openingRow['credit'],
                    'status' => OpeningBalance::STATUS_POSTED,
                    'note' => 'Demo opening balance created separately from setup masters.',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ],
            );
        }

        $parties = [
            'customer1' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'C-001'],
                ['name' => 'Rahim Traders', 'type' => 'Customer', 'receivable_account_id' => $accounts['receivable']->id, 'payable_account_id' => null, 'is_active' => true],
            ),
            'customer2' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'C-002'],
                ['name' => 'Local Vegetable Shop', 'type' => 'Customer', 'receivable_account_id' => $accounts['receivable']->id, 'payable_account_id' => null, 'is_active' => true],
            ),
            'supplier' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'S-001'],
                ['name' => 'Molla Feed Supplier', 'type' => 'Supplier', 'receivable_account_id' => null, 'payable_account_id' => $accounts['supplier_payable']->id, 'is_active' => true],
            ),
            'worker' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'W-001'],
                ['name' => 'Farm Worker Group', 'type' => 'Worker', 'receivable_account_id' => null, 'payable_account_id' => $accounts['salary_payable']->id, 'is_active' => true],
            ),
            'lender' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'L-001'],
                ['name' => 'Agrani Bank Loan', 'type' => 'Lender', 'receivable_account_id' => null, 'payable_account_id' => $accounts['loan']->id, 'is_active' => true],
            ),
            'owner' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'O-001'],
                ['name' => 'Business Owner', 'type' => 'Owner', 'receivable_account_id' => null, 'payable_account_id' => $accounts['capital']->id, 'is_active' => true],
            ),
        ];

        $this->ensureRuleTemplates((int) $company->id);

        $headDefinitions = [
            'sale' => ['TH-SALE', 'Milk and Product Sale', TransactionTypes::SALE, 'sales', ['CASH', 'CREDIT', 'PARTIAL'], 'Customer'],
            'purchase' => ['TH-PUR', 'Feed and Materials Purchase', TransactionTypes::PURCHASE, 'feed_expense', ['CASH', 'CREDIT', 'PARTIAL'], 'Supplier'],
            'customer_collection' => ['TH-COL', 'Customer Due Collection', TransactionTypes::CUSTOMER_COLLECTION, 'receivable', ['CASH'], 'Customer'],
            'supplier_payment' => ['TH-SPY', 'Supplier Due Payment', TransactionTypes::SUPPLIER_PAYMENT, 'supplier_payable', ['CASH'], 'Supplier'],
            'salary' => ['TH-EXP-SAL', 'Salary Expense', TransactionTypes::EXPENSE, 'salary', ['CASH', 'CREDIT', 'PARTIAL'], 'Worker'],
            'internet' => ['TH-EXP-NET', 'Internet and Mobile Expense', TransactionTypes::EXPENSE, 'internet', ['CASH', 'CREDIT', 'PARTIAL'], 'Any'],
            'owner_investment' => ['TH-OIN', 'Owner Investment', TransactionTypes::OWNER_INVESTMENT, 'capital', ['CASH'], 'Owner'],
            'owner_withdrawal' => ['TH-OWD', 'Owner Withdrawal', TransactionTypes::OWNER_WITHDRAWAL, 'drawing', ['CASH'], 'Owner'],
            'loan_received' => ['TH-LRV', 'Loan Received', TransactionTypes::LOAN_RECEIVED, 'loan', ['CASH'], 'Lender'],
            'loan_repayment' => ['TH-LRP', 'Loan Principal Repayment', TransactionTypes::LOAN_REPAYMENT, 'loan', ['CASH'], 'Lender'],
            'loan_interest' => ['TH-LIP', 'Loan Interest Payment', TransactionTypes::LOAN_INTEREST_PAYMENT, 'interest', ['CASH'], 'Lender'],
            'asset_purchase' => ['TH-AST', 'Vehicle or Equipment Purchase', TransactionTypes::ASSET_PURCHASE, 'vehicle_asset', ['CASH', 'CREDIT', 'PARTIAL'], 'Supplier'],
        ];

        $heads = [];
        foreach ($headDefinitions as $key => [$code, $name, $category, $accountKey, $allowedSettlements, $partyType]) {
            $heads[$key] = TransactionHead::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'accounting_rule_id' => null,
                    'posting_account_id' => $accounts[$accountKey]->id,
                    'allowed_settlements' => $allowedSettlements,
                    'party_type' => $partyType,
                    'is_active' => true,
                ],
            );
        }

        $this->ensureVoucherSequences((int) $company->id);

        if (Transaction::query()->where('company_id', $company->id)->doesntExist()) {
            $samples = [
                [TransactionTypes::SALE, 'CASH', 'sale', 'cash', null, 2500, null, 'INV-101', 'Cow milk sold in cash'],
                [TransactionTypes::SALE, 'CREDIT', 'sale', null, 'customer2', 4200, null, 'INV-102', 'Vegetables sold on credit'],
                [TransactionTypes::SALE, 'PARTIAL', 'sale', 'cash', 'customer1', 10000, 4000, 'INV-103', 'Milk sale with part payment'],
                [TransactionTypes::EXPENSE, 'CASH', 'salary', 'cash', null, 3000, null, 'PAY-201', 'Farm worker salary paid'],
                [TransactionTypes::PURCHASE, 'CREDIT', 'purchase', null, 'supplier', 8000, null, 'BILL-301', 'Feed purchased on credit'],
                [TransactionTypes::SUPPLIER_PAYMENT, 'CASH', 'supplier_payment', 'bank', 'supplier', 3000, null, 'PAY-302', 'Supplier due paid'],
                [TransactionTypes::LOAN_RECEIVED, 'CASH', 'loan_received', 'bank', 'lender', 50000, null, 'LOAN-01', 'Loan received in bank account'],
                [TransactionTypes::LOAN_REPAYMENT, 'CASH', 'loan_repayment', 'bank', 'lender', 5000, null, 'LOAN-PAY-01', 'Loan principal repaid'],
            ];

            foreach ($samples as [$category, $settlement, $headKey, $moneyKey, $partyKey, $amount, $paidAmount, $reference, $description]) {
                $postingService->post([
                    'category' => $category,
                    'settlement_type' => $settlement,
                    'transaction_date' => now()->toDateString(),
                    'transaction_head_id' => $heads[$headKey]->id,
                    'money_account_id' => $moneyKey ? $money[$moneyKey]->id : null,
                    'party_id' => $partyKey ? $parties[$partyKey]->id : null,
                    'amount' => $amount,
                    'paid_amount' => $paidAmount,
                    'reference' => $reference,
                    'description' => $description,
                    'request_token' => (string) Str::uuid(),
                ], $user);
            }
        }
    }

    private function ensureRuleTemplates(int $companyId): void
    {
        $service = app(AccountingRuleService::class);
        $settlementLabels = collect(TransactionTypes::settlementDefinitions())->map(fn (array $item) => $item['label'])->all();

        foreach (TransactionTypes::definitions() as $type => $definition) {
            foreach ($definition['allowed_settlements'] as $settlement) {
                $data = [
                    'code' => $type.'_'.$settlement,
                    'name' => $definition['label'].' — '.($settlementLabels[$settlement] ?? $settlement),
                    'category' => $type,
                    'settlement_type' => $settlement,
                    'is_active' => true,
                ];

                $existing = AccountingRule::query()
                    ->where('company_id', $companyId)
                    ->where('category', $type)
                    ->where('settlement_type', $settlement)
                    ->first();

                $existing ? $service->update($existing, $data) : $service->create($data, $companyId);
            }
        }
    }

    private function ensureVoucherSequences(int $companyId): void
    {
        $transactionTypes = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->active()
            ->orderBy('sort_order')
            ->get();

        foreach ($transactionTypes as $transactionType) {
            $prefix = (string) ($transactionType->metadata['voucher_prefix'] ?? '');
            if ($prefix === '') {
                continue;
            }

            DocumentSequence::query()->firstOrCreate(
                ['company_id' => $companyId, 'category' => $transactionType->value],
                ['prefix' => $prefix, 'next_number' => 1, 'padding' => 4],
            );
        }
    }
}
