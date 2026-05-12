<?php

namespace App\Services\Accounting;

use App\Models\FinancialYear;
use App\Models\VoucherNumberingRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $rule = VoucherNumberingRule::query()
                ->where('voucher_type', $voucherType)
                ->where('financial_year_id', $financialYear->id)
                ->where('status', 'Active')
                ->lockForUpdate()
                ->first();

            if (!$rule) {
                throw ValidationException::withMessages([
                    'voucher_number' => "Voucher numbering rule is missing for {$voucherType}. Posting is blocked.",
                ]);
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

        if (!$rule) {
            throw ValidationException::withMessages([
                'voucher_number' => "Voucher numbering rule is missing for {$voucherType}.",
            ]);
        }

        return $rule;
    }
}
