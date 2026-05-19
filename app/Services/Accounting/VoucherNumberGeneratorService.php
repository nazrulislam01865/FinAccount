<?php

namespace App\Services\Accounting;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\VoucherNumberingRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class VoucherNumberGeneratorService
{
    public function preview(
        string $voucherType,
        FinancialYear $financialYear,
        ?CarbonInterface $voucherDate = null
    ): string {
        $rule = $this->activeRule($voucherType, $financialYear);

        return $rule->generate($rule->next_number, $voucherDate);
    }

    public function reserve(
        string $voucherType,
        FinancialYear $financialYear,
        ?CarbonInterface $voucherDate = null
    ): string {
        return DB::transaction(function () use ($voucherType, $financialYear, $voucherDate) {
            $this->ensureRule($voucherType, $financialYear);

            $rule = VoucherNumberingRule::query()
                ->where('voucher_type', $voucherType)
                ->where('financial_year_id', $financialYear->id)
                ->where('status', 'Active')
                ->lockForUpdate()
                ->first();

            if (!$rule) {
                $rule = $this->ensureRule($voucherType, $financialYear);
            }

            $nextNumber = $rule->next_number;
            $voucherNumber = $rule->generate($nextNumber, $voucherDate);

            $rule->update([
                'last_number' => $nextNumber,
            ]);

            return $voucherNumber;
        });
    }

    private function activeRule(
        string $voucherType,
        FinancialYear $financialYear
    ): VoucherNumberingRule {
        $rule = VoucherNumberingRule::query()
            ->where('voucher_type', $voucherType)
            ->where('financial_year_id', $financialYear->id)
            ->where('status', 'Active')
            ->first();

        return $rule ?: $this->ensureRule($voucherType, $financialYear);
    }

    private function ensureRule(string $voucherType, FinancialYear $financialYear): VoucherNumberingRule
    {
        $companyId = Company::query()->value('id');
        $prefix = VoucherNumberingRule::DEFAULT_PREFIXES[$voucherType]
            ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $voucherType), 0, 2))
            ?: 'VN';

        return VoucherNumberingRule::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'financial_year_id' => $financialYear->id,
                'voucher_type' => $voucherType,
            ],
            [
                'prefix' => $prefix,
                'format_template' => $prefix . '-{YYYY}-{00000}',
                'starting_number' => 1,
                'number_length' => 5,
                'last_number' => 0,
                'reset_every_year' => true,
                'used_for' => $this->usedFor($voucherType),
                'status' => 'Active',
                'created_by' => null,
                'updated_by' => null,
            ]
        );
    }

    private function usedFor(string $voucherType): string
    {
        return match ($voucherType) {
            'Payment Voucher' => 'Cash/bank payments',
            'Receipt Voucher' => 'Cash/bank receipts',
            'Journal Voucher' => 'Non-cash journal entries',
            'Contra / Transfer Voucher' => 'Cash/bank transfers',
            'Draft Voucher' => 'Unposted draft transactions',
            'Opening Voucher' => 'Opening balance posting',
            default => $voucherType,
        };
    }
}
