<?php

namespace Database\Seeders;

use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HisebGhorDemoSeeder extends Seeder
{
    public function run(TransactionPostingService $postingService): void
    {
        $company = Company::query()->updateOrCreate(
            ['code' => 'HG-DEMO'],
            [
                'name' => 'HisebGhor Demo Company',
                'currency_code' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'status' => 'active',
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@hisebghor.test'],
            [
                'company_id' => $company->id,
                'name' => 'HisebGhor Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $accounts = [
            'cash' => ['1111', 'Cash in Hand', 'Asset', 'Debit'],
            'bank' => ['1112', 'BRAC Bank Current Account', 'Asset', 'Debit'],
            'bkash' => ['1113', 'bKash Business Account', 'Asset', 'Debit'],
            'receivable' => ['1121', 'Customer Receivable', 'Asset', 'Debit'],
            'feed_stock' => ['1211', 'Farm Materials / Feed Stock', 'Asset', 'Debit'],
            'supplier_payable' => ['2111', 'Supplier Payable', 'Liability', 'Credit'],
            'loan' => ['2211', 'Loan from Bank / Lender', 'Liability', 'Credit'],
            'capital' => ['3111', 'Owner Capital', 'Equity', 'Credit'],
            'sales' => ['4111', 'Farm Product Sales Income', 'Income', 'Credit'],
            'other_income' => ['4199', 'Other Operating Income', 'Income', 'Credit'],
            'salary' => ['5111', 'Farm Worker Salary Expense', 'Expense', 'Debit'],
            'feed_expense' => ['5121', 'Cow/Fish Feed Expense', 'Expense', 'Debit'],
            'internet' => ['5131', 'Internet & Mobile Bill Expense', 'Expense', 'Debit'],
            'stationery' => ['5141', 'Stationery Expense', 'Expense', 'Debit'],
            'interest' => ['5211', 'Loan Interest Expense', 'Expense', 'Debit'],
        ];

        foreach ($accounts as $key => [$code, $name, $type, $normal]) {
            $accounts[$key] = ChartOfAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'normal_balance' => $normal, 'is_active' => true],
            );
        }

        $money = [
            'cash' => MoneyAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Main Cash Box'],
                ['chart_of_account_id' => $accounts['cash']->id, 'kind' => 'Cash', 'opening_balance' => 5000, 'is_active' => true],
            ),
            'bank' => MoneyAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'BRAC Bank - Farm Account'],
                ['chart_of_account_id' => $accounts['bank']->id, 'kind' => 'Bank', 'opening_balance' => 25000, 'is_active' => true],
            ),
            'bkash' => MoneyAccount::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'bKash Merchant Wallet'],
                ['chart_of_account_id' => $accounts['bkash']->id, 'kind' => 'Digital', 'opening_balance' => 3000, 'is_active' => true],
            ),
        ];

        $parties = [
            'customer1' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'C-001'],
                ['name' => 'Rahim Traders', 'type' => 'Customer', 'receivable_account_id' => $accounts['receivable']->id, 'is_active' => true],
            ),
            'customer2' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'C-002'],
                ['name' => 'Local Vegetable Shop', 'type' => 'Customer', 'receivable_account_id' => $accounts['receivable']->id, 'is_active' => true],
            ),
            'supplier' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'S-001'],
                ['name' => 'Molla Feed Supplier', 'type' => 'Supplier', 'payable_account_id' => $accounts['supplier_payable']->id, 'is_active' => true],
            ),
            'worker' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'W-001'],
                ['name' => 'Farm Worker Group', 'type' => 'Worker', 'is_active' => true],
            ),
            'lender' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'L-001'],
                ['name' => 'Agrani Bank Loan', 'type' => 'Lender', 'payable_account_id' => $accounts['loan']->id, 'is_active' => true],
            ),
            'owner' => Party::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'O-001'],
                ['name' => 'Business Owner', 'type' => 'Owner', 'payable_account_id' => $accounts['capital']->id, 'is_active' => true],
            ),
        ];

        $rules = [
            'sale_cash' => ['R-SAL-01', 'Immediate Sale - Receive Money', 'Sales', AccountingRule::SOURCE_SELECTED_MONEY, AccountingRule::SOURCE_HEAD_ACCOUNT, false, 'Any', true],
            'sale_credit' => ['R-SAL-02', 'Credit Sale - Customer Receivable', 'Sales', AccountingRule::SOURCE_PARTY_RECEIVABLE, AccountingRule::SOURCE_HEAD_ACCOUNT, true, 'Customer', false],
            'expense' => ['R-PAY-01', 'Expense Payment - Money Out', 'Payment', AccountingRule::SOURCE_HEAD_ACCOUNT, AccountingRule::SOURCE_SELECTED_MONEY, false, 'Any', true],
            'supplier_payment' => ['R-PAY-02', 'Supplier Due Payment', 'Payment', AccountingRule::SOURCE_PARTY_PAYABLE, AccountingRule::SOURCE_SELECTED_MONEY, true, 'Supplier', true],
            'credit_purchase' => ['R-LIA-01', 'Credit Purchase - Increase Supplier Payable', 'Liability', AccountingRule::SOURCE_HEAD_ACCOUNT, AccountingRule::SOURCE_PARTY_PAYABLE, true, 'Supplier', false],
            'loan_received' => ['R-LIA-02', 'Loan Received - Increase Loan Payable', 'Liability', AccountingRule::SOURCE_SELECTED_MONEY, AccountingRule::SOURCE_PARTY_PAYABLE, true, 'Lender', true],
            'loan_repayment' => ['R-LIA-03', 'Loan Repayment - Reduce Loan Payable', 'Liability', AccountingRule::SOURCE_PARTY_PAYABLE, AccountingRule::SOURCE_SELECTED_MONEY, true, 'Lender', true],
        ];

        foreach ($rules as $key => [$code, $name, $category, $debit, $credit, $partyRequired, $partyType, $moneyRequired]) {
            $rules[$key] = AccountingRule::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                compact('name', 'category') + [
                    'debit_source' => $debit,
                    'credit_source' => $credit,
                    'party_required' => $partyRequired,
                    'party_type' => $partyType,
                    'money_required' => $moneyRequired,
                    'is_active' => true,
                ],
            );
        }

        $heads = [
            'milk_cash' => ['TH-S-001', 'Milk Sale - Immediate Payment', 'Sales', 'sale_cash', 'sales'],
            'fish_cash' => ['TH-S-002', 'Fish Sale - Immediate Payment', 'Sales', 'sale_cash', 'sales'],
            'vegetable_credit' => ['TH-S-003', 'Vegetable Sale - Credit', 'Sales', 'sale_credit', 'sales'],
            'salary' => ['TH-P-001', 'Farm Worker Salary Payment', 'Payment', 'expense', 'salary'],
            'internet' => ['TH-P-002', 'Internet & Mobile Bill Payment', 'Payment', 'expense', 'internet'],
            'supplier_payment' => ['TH-P-003', 'Supplier Due Payment', 'Payment', 'supplier_payment', 'supplier_payable'],
            'feed_credit' => ['TH-L-001', 'Feed Purchase on Credit', 'Liability', 'credit_purchase', 'feed_expense'],
            'loan_received' => ['TH-L-002', 'Loan Received from Bank/Lender', 'Liability', 'loan_received', 'loan'],
            'loan_repayment' => ['TH-L-003', 'Loan Principal Repayment', 'Liability', 'loan_repayment', 'loan'],
        ];

        foreach ($heads as $key => [$code, $name, $category, $ruleKey, $accountKey]) {
            $heads[$key] = TransactionHead::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'accounting_rule_id' => $rules[$ruleKey]->id,
                    'posting_account_id' => $accounts[$accountKey]->id,
                    'is_active' => true,
                ],
            );
        }

        foreach (['Sales' => 'SAL', 'Payment' => 'PAY', 'Liability' => 'LIA'] as $category => $prefix) {
            DocumentSequence::query()->firstOrCreate(
                ['company_id' => $company->id, 'category' => $category],
                ['prefix' => $prefix, 'next_number' => 1, 'padding' => 4],
            );
        }

        if (Transaction::query()->where('company_id', $company->id)->doesntExist()) {
            $samples = [
                ['Sales', 'milk_cash', 'cash', null, 2500, 'INV-101', 'Cow milk sold in cash'],
                ['Sales', 'vegetable_credit', null, 'customer2', 4200, 'INV-102', 'Vegetables sold on credit'],
                ['Payment', 'salary', 'cash', null, 3000, 'PAY-201', 'Farm worker salary paid'],
                ['Liability', 'feed_credit', null, 'supplier', 8000, 'BILL-301', 'Fish and cow feed purchased on credit'],
                ['Payment', 'supplier_payment', 'bank', 'supplier', 3000, 'PAY-302', 'Partial supplier due paid'],
                ['Liability', 'loan_received', 'bank', 'lender', 50000, 'LOAN-01', 'Loan received in bank account'],
                ['Liability', 'loan_repayment', 'bank', 'lender', 5000, 'LOAN-PAY-01', 'Loan principal repaid'],
            ];

            foreach ($samples as [$category, $headKey, $moneyKey, $partyKey, $amount, $reference, $description]) {
                $postingService->post([
                    'category' => $category,
                    'transaction_date' => now()->toDateString(),
                    'transaction_head_id' => $heads[$headKey]->id,
                    'money_account_id' => $moneyKey ? $money[$moneyKey]->id : null,
                    'party_id' => $partyKey ? $parties[$partyKey]->id : null,
                    'amount' => $amount,
                    'reference' => $reference,
                    'description' => $description,
                    'request_token' => (string) Str::uuid(),
                ], $user);
            }
        }
    }
}
