<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $type = fn ($name) => AccountType::where('name', $name)->value('id');

        $accounts = [
            ['1000', 'Assets', 'Asset', 'Group', false, false],
            ['1010', 'Cash in Hand', 'Asset', 'Ledger', true, true],
            ['1020', 'BRAC Bank Current Account', 'Asset', 'Ledger', true, true],
            ['1030', 'City Bank Current Account', 'Asset', 'Ledger', true, true],
            ['1040', 'bKash Merchant Wallet', 'Asset', 'Ledger', true, true],
            ['1100', 'Accounts Receivable', 'Asset', 'Ledger', false, true],
            ['1200', 'Advance to Supplier / Employee', 'Asset', 'Ledger', false, true],
            ['2000', 'Liabilities', 'Liability', 'Group', false, false],
            ['2010', 'Accounts Payable', 'Liability', 'Ledger', false, true],
            ['2020', 'Salary Payable', 'Liability', 'Ledger', false, true],
            ['2030', 'Advance from Customer', 'Liability', 'Ledger', false, true],
            ['3000', 'Equity', 'Equity', 'Group', false, false],
            ['3010', 'Owner Capital', 'Equity', 'Ledger', false, true],
            ['4000', 'Income', 'Income', 'Group', false, false],
            ['4010', 'Vehicle Rent Income', 'Income', 'Ledger', false, true],
            ['4020', 'Service Income', 'Income', 'Ledger', false, true],
            ['5000', 'Expenses', 'Expense', 'Group', false, false],
            ['5010', 'Salary Expense', 'Expense', 'Ledger', false, true],
            ['5020', 'Fuel Expense', 'Expense', 'Ledger', false, true],
            ['5030', 'Maintenance Expense', 'Expense', 'Ledger', false, true],
            ['5040', 'Office Rent Expense', 'Expense', 'Ledger', false, true],
        ];

        foreach ($accounts as [$code, $name, $typeName, $level, $cashBank, $postingAllowed]) {
            $accountTypeId = $type($typeName);
            $normalBalance = AccountType::whereKey($accountTypeId)->value('normal_balance');

            ChartOfAccount::updateOrCreate(
                ['account_code' => $code],
                [
                    'account_name' => $name,
                    'account_level' => $level,
                    'account_type_id' => $accountTypeId,
                    'normal_balance' => $normalBalance,
                    'is_cash_bank' => $cashBank,
                    'posting_allowed' => $postingAllowed,
                    'status' => 'Active',
                ]
            );
        }
    }
}
