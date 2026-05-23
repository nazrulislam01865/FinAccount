<?php

namespace App\AccountingEngine\Services;

use App\AccountingEngine\DTO\TransactionInput;
use App\Models\AccountingRule;
use App\Models\Company;
use App\Models\LedgerMappingRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RuleResolver
{
    public function resolve(TransactionInput|array $input): AccountingRule|LedgerMappingRule
    {
        $companyId = $this->companyId($input);
        $transactionHeadId = $this->intValue($input, 'transactionHeadId', 'transaction_head_id');
        $settlementTypeId = $this->nullableIntValue($input, 'settlementTypeId', 'settlement_type_id');

        $rule = AccountingRule::query()
            ->with([
                'lines.ledger.accountType',
                'transactionHead.defaultPrimaryLedger.accountType',
                'settlementType',
                'partyType',
                'legacyLedgerMappingRule.debitAccount.accountType',
                'legacyLedgerMappingRule.creditAccount.accountType',
            ])
            ->where('company_id', $companyId)
            ->where('transaction_head_id', $transactionHeadId)
            ->where('status', 'Active')
            ->where(function ($query) use ($settlementTypeId) {
                if ($settlementTypeId) {
                    $query->where('settlement_type_id', $settlementTypeId)
                        ->orWhereNull('settlement_type_id');
                } else {
                    $query->whereNull('settlement_type_id');
                }
            })
            ->orderByRaw('CASE WHEN settlement_type_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        if ($rule) {
            return $rule;
        }

        $legacy = LedgerMappingRule::query()
            ->with([
                'transactionHead',
                'settlementType',
                'debitAccount.accountType',
                'creditAccount.accountType',
            ])
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where('transaction_head_id', $transactionHeadId)
            ->where('settlement_type_id', $settlementTypeId)
            ->where('status', 'Active')
            ->first();

        if ($legacy) {
            return $legacy;
        }

        throw ValidationException::withMessages([
            'ledger_mapping' => 'No active accounting rule is configured for this transaction purpose and settlement type.',
        ]);
    }

    public function isV2Rule(Model $rule): bool
    {
        return $rule instanceof AccountingRule;
    }

    private function companyId(TransactionInput|array $input): int
    {
        $companyId = $this->intValue($input, 'companyId', 'company_id');

        if ($companyId > 0) {
            return $companyId;
        }

        return (int) Company::query()->orderBy('id')->value('id');
    }

    private function intValue(TransactionInput|array $input, string $objectProperty, string $arrayKey): int
    {
        if ($input instanceof TransactionInput) {
            return (int) $input->{$objectProperty};
        }

        return (int) ($input[$arrayKey] ?? $input[$objectProperty] ?? 0);
    }

    private function nullableIntValue(TransactionInput|array $input, string $objectProperty, string $arrayKey): ?int
    {
        $value = $this->intValue($input, $objectProperty, $arrayKey);

        return $value > 0 ? $value : null;
    }
}
