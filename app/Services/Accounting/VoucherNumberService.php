<?php

namespace App\Services\Accounting;

use App\Models\DocumentSequence;
use Illuminate\Validation\ValidationException;

class VoucherNumberService
{
    public function lock(int $companyId, string $category): DocumentSequence
    {
        $sequence = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('category', $category)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            throw ValidationException::withMessages([
                'category' => 'The voucher sequence for this transaction category is not configured.',
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
