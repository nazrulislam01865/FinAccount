<?php

namespace App\Services\Accounting;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\VoucherHeader;
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
        $nextNumber = max((int) $rule->next_number, 1);
        $voucherNumber = $rule->generate($nextNumber, $voucherDate);

        while (VoucherHeader::query()->where('voucher_number', $voucherNumber)->exists()) {
            $nextNumber++;
            $voucherNumber = $rule->generate($nextNumber, $voucherDate);
        }

        return $voucherNumber;
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

            $nextNumber = max((int) $rule->next_number, 1);
            $voucherNumber = $rule->generate($nextNumber, $voucherDate);

            while (VoucherHeader::query()->where('voucher_number', $voucherNumber)->exists()) {
                $nextNumber++;
                $voucherNumber = $rule->generate($nextNumber, $voucherDate);
            }

            $rule->update([
                'last_number' => $nextNumber,
                'status' => 'Active',
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

        $rule = VoucherNumberingRule::query()->firstOrCreate(
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

        if ($rule->status !== 'Active') {
            $rule->forceFill([
                'status' => 'Active',
                'prefix' => $rule->prefix ?: $prefix,
                'format_template' => $rule->format_template ?: $prefix . '-{YYYY}-{00000}',
                'number_length' => $rule->number_length ?: 5,
                'used_for' => $rule->used_for ?: $this->usedFor($voucherType),
            ])->save();
        }

        return $rule->fresh();
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
