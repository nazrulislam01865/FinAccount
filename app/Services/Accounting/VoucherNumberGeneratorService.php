<?php

namespace App\Services\Accounting;

use App\Models\FinancialYear;
use App\Models\VoucherNumberingRule;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
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
            $rule = $this->lockedActiveRule($voucherType, $financialYear);
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

        return $rule ?: $this->createDefaultRule($voucherType, $financialYear);
    }

    private function lockedActiveRule(
        string $voucherType,
        FinancialYear $financialYear
    ): VoucherNumberingRule {
        $rule = VoucherNumberingRule::query()
            ->where('voucher_type', $voucherType)
            ->where('financial_year_id', $financialYear->id)
            ->where('status', 'Active')
            ->lockForUpdate()
            ->first();

        if ($rule) {
            return $rule;
        }

        $this->createDefaultRule($voucherType, $financialYear);

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

        return $rule;
    }

    private function createDefaultRule(
        string $voucherType,
        FinancialYear $financialYear
    ): VoucherNumberingRule {
        $prefix = VoucherNumberingRule::DEFAULT_PREFIXES[$voucherType]
            ?? $this->prefixFromVoucherType($voucherType);

        $attributes = [
            'company_id' => $financialYear->company_id,
            'financial_year_id' => $financialYear->id,
            'voucher_type' => $voucherType,
        ];

        $defaults = [
            'prefix' => $prefix,
            'format_template' => $prefix . '-{YYYY}-{00000}',
            'starting_number' => 1,
            'number_length' => 5,
            'last_number' => 0,
            'reset_every_year' => true,
            'used_for' => 'Auto-created for transaction posting.',
            'status' => 'Active',
        ];

        try {
            $rule = VoucherNumberingRule::query()->where($attributes)->first();

            if ($rule) {
                $rule->fill([
                    'prefix' => $rule->prefix ?: $defaults['prefix'],
                    'format_template' => $rule->format_template ?: $defaults['format_template'],
                    'starting_number' => $rule->starting_number ?: $defaults['starting_number'],
                    'number_length' => $rule->number_length ?: $defaults['number_length'],
                    'last_number' => $rule->last_number ?? $defaults['last_number'],
                    'reset_every_year' => $rule->reset_every_year ?? $defaults['reset_every_year'],
                    'used_for' => $rule->used_for ?: $defaults['used_for'],
                    'status' => 'Active',
                ])->save();

                return $rule->refresh();
            }

            return VoucherNumberingRule::query()->create($attributes + $defaults);
        } catch (QueryException) {
            $rule = VoucherNumberingRule::query()->where($attributes)->first();

            if ($rule) {
                return $rule;
            }

            throw ValidationException::withMessages([
                'voucher_number' => "Voucher numbering rule could not be created for {$voucherType}.",
            ]);
        }
    }

    private function prefixFromVoucherType(string $voucherType): string
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($voucherType));

        return substr($letters ?: 'VN', 0, 6);
    }
}
