<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use Illuminate\Validation\ValidationException;

class RuleMatcher
{
    public function match(int $companyId, string $transactionType, string $settlementType): AccountingRule
    {
        $rule = AccountingRule::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->where('category', $transactionType)
            ->where('settlement_type', $settlementType)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $rule) {
            throw ValidationException::withMessages([
                'settlement_type' => 'Accounting setup is incomplete for the selected transaction type and payment type. Ask an administrator to activate the matching accounting accounting rule.',
            ]);
        }

        return $rule;
    }
}
