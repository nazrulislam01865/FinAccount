<?php

namespace App\Services\Accounting;

use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;

class TransactionVoucherTypeService
{
    public function resolve(
        TransactionHead $transactionHead,
        SettlementType $settlementType,
        ?LedgerMappingRule $mappingRule = null,
        array $entries = [],
        bool $draft = false
    ): string {
        if ($draft) {
            return 'Draft Voucher';
        }

        $cashBankShape = $this->cashBankShape($mappingRule, $entries);

        if ($cashBankShape['debit'] && $cashBankShape['credit']) {
            return 'Contra / Transfer Voucher';
        }

        if ($cashBankShape['debit']) {
            return 'Receipt Voucher';
        }

        if ($cashBankShape['credit']) {
            return 'Payment Voucher';
        }

        $settlementKey = $this->settlementKey($settlementType);

        if (in_array($settlementKey, ['due', 'adjustment'], true)) {
            return 'Journal Voucher';
        }

        $headText = strtoupper(trim($transactionHead->nature . ' ' . $transactionHead->name));

        if (str_contains($headText, 'RECEIPT') || str_contains($headText, 'RECEIVED') || str_contains($headText, 'COLLECTION')) {
            return 'Receipt Voucher';
        }

        if (str_contains($headText, 'PAYMENT') || str_contains($headText, 'PAID') || str_contains($headText, 'EXPENSE')) {
            return 'Payment Voucher';
        }

        return 'Journal Voucher';
    }

    private function cashBankShape(?LedgerMappingRule $mappingRule, array $entries): array
    {
        $debitCashBank = false;
        $creditCashBank = false;

        foreach ($entries as $entry) {
            if (!($entry['is_cash_bank_account'] ?? false)) {
                continue;
            }

            if (($entry['entry_type'] ?? null) === 'Debit') {
                $debitCashBank = true;
            }

            if (($entry['entry_type'] ?? null) === 'Credit') {
                $creditCashBank = true;
            }
        }

        if (!$debitCashBank && !$creditCashBank && $mappingRule) {
            $debitCashBank = (bool) $mappingRule->debitAccount?->is_cash_bank;
            $creditCashBank = (bool) $mappingRule->creditAccount?->is_cash_bank;
        }

        return [
            'debit' => $debitCashBank,
            'credit' => $creditCashBank,
        ];
    }

    private function settlementKey(SettlementType $settlementType): string
    {
        $value = strtoupper(trim($settlementType->code . ' ' . $settlementType->name));

        return match (true) {
            str_contains($value, 'ADVANCE_PAID') || str_contains($value, 'ADVANCE PAID') => 'advance_paid',
            str_contains($value, 'ADVANCE_RECEIVED') || str_contains($value, 'ADVANCE RECEIVED') => 'advance_received',
            str_contains($value, 'CASH') => 'cash',
            str_contains($value, 'BANK') => 'bank',
            str_contains($value, 'DUE') => 'due',
            str_contains($value, 'ADJUST') => 'adjustment',
            default => 'other',
        };
    }
}
