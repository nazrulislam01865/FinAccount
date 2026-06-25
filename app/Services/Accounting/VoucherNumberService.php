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
            ->lockForUpdate()
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
            $metadata = is_array($categoryOption->metadata) ? $categoryOption->metadata : [];
            $prefix = strtoupper(trim((string) ($metadata['voucher_prefix'] ?? '')));

            if (! preg_match('/^[A-Z0-9]{2,10}$/', $prefix)) {
                throw ValidationException::withMessages([
                    'category' => 'Voucher numbering cannot be created automatically because this transaction type has no valid voucher prefix. Edit the Transaction Type first.',
                ]);
            }

            $prefixInUse = DocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('prefix', $prefix)
                ->where('category', '!=', $category)
                ->exists();

            if ($prefixInUse) {
                throw ValidationException::withMessages([
                    'category' => 'The configured voucher prefix is already used by another category in this company.',
                ]);
            }

            $now = now();
            DocumentSequence::query()->insertOrIgnore([
                'company_id' => $companyId,
                'category' => $category,
                'prefix' => $prefix,
                'next_number' => 1,
                'padding' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('category', $category)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
        }

        if (! $sequence) {
            throw ValidationException::withMessages([
                'category' => 'Voucher numbering could not be created for this category.',
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
