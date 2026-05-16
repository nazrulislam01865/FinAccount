<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\VoucherNumberingRule;
use Illuminate\Database\Seeder;

class VoucherNumberingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();

        if (!$company) {
            return;
        }

        $financialYear = FinancialYear::query()
            ->where('company_id', $company->id)
            ->where('status', 'Active')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->first();

        if (!$financialYear) {
            return;
        }

        $rules = [
            ['Payment Voucher', 'PV', 'PV-{YYYY}-{00000}', 'Cash/bank payments'],
            ['Receipt Voucher', 'RV', 'RV-{YYYY}-{00000}', 'Cash/bank receipts'],
            ['Journal Voucher', 'JV', 'JV-{YYYY}-{00000}', 'Due, adjustment, opening balance'],
            ['Contra / Transfer Voucher', 'CV', 'CV-{YYYY}-{00000}', 'Cash to bank or bank to bank transfer'],
            ['Draft Voucher', 'DR', 'DR-{YYYY}-{00000}', 'Unposted draft transactions'],
        ];

        foreach ($rules as [$type, $prefix, $format, $usedFor]) {
            VoucherNumberingRule::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'financial_year_id' => $financialYear->id,
                    'voucher_type' => $type,
                ],
                [
                    'prefix' => $prefix,
                    'format_template' => $format,
                    'starting_number' => 1,
                    'number_length' => 5,
                    'last_number' => 0,
                    'reset_every_year' => true,
                    'used_for' => $usedFor,
                    'status' => 'Active',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }
    }
}
