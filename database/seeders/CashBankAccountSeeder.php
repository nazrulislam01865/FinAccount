<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CashBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();
        $bank = Bank::query()->where('bank_name', 'BRAC Bank')->first();

        $items = [
            [
                'cash_bank_code' => 'CB-001',
                'cash_bank_name' => 'Office Cash',
                'type' => 'Cash',
                'ledger_code' => '1010',
                'bank_id' => null,
                'bank_name' => null,
                'branch_name' => null,
                'account_number' => null,
                'usage_note' => 'Default cash box for cash receipts and payments.',
            ],
            [
                'cash_bank_code' => 'BK-001',
                'cash_bank_name' => 'BRAC Bank',
                'type' => 'Bank',
                'ledger_code' => '1020',
                'bank_id' => $bank?->id,
                'bank_name' => 'BRAC Bank',
                'branch_name' => 'Main Branch',
                'account_number' => '1000000000001',
                'usage_note' => 'Default bank account for bank receipts and payments.',
            ],
        ];

        foreach ($items as $item) {
            $ledger = ChartOfAccount::query()
                ->where('account_code', $item['ledger_code'])
                ->where('status', 'Active')
                ->first();

            if (!$ledger) {
                continue;
            }

            CashBankAccount::query()->updateOrCreate(
                ['cash_bank_name' => $item['cash_bank_name']],
                [
                    'company_id' => $company?->id,
                    'cash_bank_name' => $item['cash_bank_name'],
                    'type' => $item['type'],
                    'linked_ledger_account_id' => $ledger->id,
                    'bank_id' => $item['bank_id'],
                    'bank_name' => $item['bank_name'],
                    'branch_name' => $item['branch_name'],
                    'account_number' => $item['account_number'],
                    'opening_balance' => 0,
                    'usage_note' => $item['usage_note'],
                    'status' => 'Active',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }
    }
}
