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
            ['1000', 'Cash', 'Asset', true], ['1010', 'Bank - Operating A/C', 'Asset', true],
            ['1100', 'Accounts Receivable', 'Asset', false], ['1200', 'Advance to Supplier', 'Asset', false],
            ['1300', 'Advance from Customer', 'Liability', false], ['2000', 'Accounts Payable', 'Liability', false],
            ['2100', 'Salary Payable', 'Liability', false], ['3000', 'Salary Expense', 'Expense', false],
            ['3010', 'Fuel Expense', 'Expense', false], ['3020', 'Vehicle Maintenance Expense', 'Expense', false],
            ['4000', 'Rent Income', 'Income', false], ['5000', 'Owner Capital', 'Equity', false],
        ];

        foreach ($accounts as [$code, $name, $typeName, $cashBank]) {
            ChartOfAccount::updateOrCreate(
                ['account_code' => $code],
                ['account_name' => $name, 'account_type_id' => $type($typeName), 'is_cash_bank' => $cashBank, 'status' => 'Active']
            );
        }
    }
}
