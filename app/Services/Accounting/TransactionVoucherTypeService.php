<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;

class TransactionVoucherTypeService
{
    public function resolve(
        ?TransactionHead $transactionHead,
        ?SettlementType $settlementType = null,
        LedgerMappingRule|AccountingRule|null $mappingRule = null,
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

        if (in_array($settlementKey, ['cash', 'bank', 'advance_received'], true)) {
            return 'Receipt Voucher';
        }

        if (in_array($settlementKey, ['advance_paid'], true)) {
            return 'Payment Voucher';
        }

        $headText = $this->headText($transactionHead);

        if (
            str_contains($headText, 'OPENING') ||
            str_contains($headText, 'OPENING BALANCE')
        ) {
            return 'Opening Voucher';
        }

        if (
            str_contains($headText, 'CONTRA') ||
            str_contains($headText, 'TRANSFER') ||
            str_contains($headText, 'CASH TO BANK') ||
            str_contains($headText, 'BANK TO CASH') ||
            str_contains($headText, 'BANK TO BANK')
        ) {
            return 'Contra / Transfer Voucher';
        }

        if (
            str_contains($headText, 'SALES') ||
            str_contains($headText, 'SALE') ||
            str_contains($headText, 'INCOME')
        ) {
            return 'Sales Voucher';
        }

        if (
            str_contains($headText, 'PURCHASE') ||
            str_contains($headText, 'SUPPLIER BILL')
        ) {
            return 'Purchase Voucher';
        }

        if (
            str_contains($headText, 'RECEIPT') ||
            str_contains($headText, 'RECEIVED') ||
            str_contains($headText, 'RECEIVE') ||
            str_contains($headText, 'COLLECTION') ||
            str_contains($headText, 'CUSTOMER COLLECTION')
        ) {
            return 'Receipt Voucher';
        }

        if (
            str_contains($headText, 'PAYMENT') ||
            str_contains($headText, 'PAID') ||
            str_contains($headText, 'PAY') ||
            str_contains($headText, 'EXPENSE') ||
            str_contains($headText, 'SUPPLIER PAYMENT')
        ) {
            return 'Payment Voucher';
        }

        return 'Journal Voucher';
    }

    /**
     * Detect whether the generated journal touches cash/bank on debit or credit side.
     *
     * Supports both:
     * - Phase 3 V2 AccountingRule
     * - Legacy LedgerMappingRule fallback
     *
     * @param LedgerMappingRule|AccountingRule|null $mappingRule
     * @param array<int, array<string, mixed>> $entries
     * @return array{debit: bool, credit: bool}
     */
    private function cashBankShape(LedgerMappingRule|AccountingRule|null $mappingRule, array $entries): array
    {
        $debitCashBank = false;
        $creditCashBank = false;

        foreach ($entries as $entry) {
            $isCashBank = (bool) (
                $entry['is_cash_bank_account']
                ?? $entry['is_cash_bank']
                ?? false
            );

            if (! $isCashBank) {
                continue;
            }

            $entryType = strtoupper((string) ($entry['entry_type'] ?? $entry['side'] ?? ''));

            $debitAmount = (float) ($entry['debit'] ?? 0);
            $creditAmount = (float) ($entry['credit'] ?? 0);

            if ($entryType === 'DEBIT' || $debitAmount > 0) {
                $debitCashBank = true;
            }

            if ($entryType === 'CREDIT' || $creditAmount > 0) {
                $creditCashBank = true;
            }
        }

        if (! $debitCashBank && ! $creditCashBank && $mappingRule instanceof LedgerMappingRule) {
            $debitCashBank = (bool) (
                $mappingRule->debitAccount?->is_cash_bank
                ?? $mappingRule->debitAccount?->is_cash_bank_account
                ?? false
            );

            $creditCashBank = (bool) (
                $mappingRule->creditAccount?->is_cash_bank
                ?? $mappingRule->creditAccount?->is_cash_bank_account
                ?? false
            );
        }

        if (! $debitCashBank && ! $creditCashBank && $mappingRule instanceof AccountingRule) {
            $mappingRule->loadMissing('lines.ledger');

            foreach ($mappingRule->lines as $line) {
                $isCashBankLedger = (bool) (
                    $line->ledger?->is_cash_bank
                    ?? $line->ledger?->is_cash_bank_account
                    ?? false
                );

                $isUserCashBankSource = $line->ledger_source === 'user_cash_bank';

                if (! $isCashBankLedger && ! $isUserCashBankSource) {
                    continue;
                }

                if ($line->side === 'Debit') {
                    $debitCashBank = true;
                }

                if ($line->side === 'Credit') {
                    $creditCashBank = true;
                }
            }
        }

        return [
            'debit' => $debitCashBank,
            'credit' => $creditCashBank,
        ];
    }

    private function settlementKey(?SettlementType $settlementType): string
    {
        if (! $settlementType) {
            return 'other';
        }

        $value = strtoupper(trim(
            (string) $settlementType->code . ' ' . (string) $settlementType->name
        ));

        return match (true) {
            str_contains($value, 'ADVANCE_PAID') ||
            str_contains($value, 'ADVANCE PAID') => 'advance_paid',

            str_contains($value, 'ADVANCE_RECEIVED') ||
            str_contains($value, 'ADVANCE RECEIVED') ||
            str_contains($value, 'ADVANCE RECEIVE') => 'advance_received',

            str_contains($value, 'CASH') => 'cash',
            str_contains($value, 'BANK') => 'bank',
            str_contains($value, 'DUE') => 'due',
            str_contains($value, 'ADJUST') => 'adjustment',

            default => 'other',
        };
    }

    private function headText(?TransactionHead $transactionHead): string
    {
        if (! $transactionHead) {
            return '';
        }

        return strtoupper(trim(implode(' ', array_filter([
            $transactionHead->nature ?? null,
            $transactionHead->name ?? null,
            $transactionHead->category ?? null,
            $transactionHead->transaction_screen ?? null,
            $transactionHead->default_movement ?? null,
        ]))));
    }
}