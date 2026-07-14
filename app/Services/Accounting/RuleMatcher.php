<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\TransactionHead;
use Illuminate\Validation\ValidationException;

class RuleMatcher
{
    public function match(
        int $companyId,
        string $transactionType,
        string $settlementType,
        TransactionHead|int|null $transactionHead = null,
    ): AccountingRule {
        $headId = $transactionHead instanceof TransactionHead
            ? (int) $transactionHead->id
            : ($transactionHead ? (int) $transactionHead : null);

        $baseQuery = AccountingRule::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(category) = ?', [strtolower($transactionType)])
            ->where('settlement_type', $settlementType)
            ->where('is_active', true);

        if ($headId) {
            $headRule = (clone $baseQuery)
                ->where('transaction_head_id', $headId)
                ->orderBy('id')
                ->first();

            if ($headRule) {
                return $headRule;
            }
        }

        $rule = $baseQuery
            ->whereNull('transaction_head_id')
            ->orderBy('id')
            ->first();

        if (! $rule) {
            throw ValidationException::withMessages([
                'settlement_type' => $headId
                    ? 'Accounting setup is incomplete for the selected transaction head and payment type. Activate or create the matching head-specific accounting rule.'
                    : 'Accounting setup is incomplete for the selected transaction type and payment type. Ask an administrator to activate the matching accounting rule.',
            ]);
        }

        return $rule;
    }
}
