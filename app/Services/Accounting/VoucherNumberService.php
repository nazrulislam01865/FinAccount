<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\DocumentSequence;
use Illuminate\Validation\ValidationException;

class VoucherNumberService
{
    public function lock(int $companyId, string $category): DocumentSequence
    {
        $categoryOption = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->active()
            ->where('value', $category)
            ->first();

        if (! $categoryOption) {
            throw ValidationException::withMessages([
                'category' => 'The selected transaction category is not configured.',
            ]);
        }

        $sequence = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('category', $category)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            throw ValidationException::withMessages([
                'category' => 'Voucher numbering is not configured for this category. Add it from Master > Voucher Numbering.',
            ]);
        }

        return $sequence;
    }

    public function issue(DocumentSequence $sequence): string
    {
        $voucher = $sequence->prefix.'-'.str_pad(
            (string) $sequence->next_number,
            $sequence->padding,
            '0',
            STR_PAD_LEFT,
        );

        $sequence->increment('next_number');

        return $voucher;
    }
}
