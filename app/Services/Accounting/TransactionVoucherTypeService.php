<?php

namespace App\Services\Accounting;

use App\Models\SettlementType;
use App\Models\TransactionHead;

class TransactionVoucherTypeService
{
    public function resolve(
        TransactionHead $transactionHead,
        SettlementType $settlementType,
        ?string $overrideVoucherType = null,
        bool $draft = false
    ): string {
        if ($draft) {
            return 'Draft Voucher';
        }

        $overrideVoucherType = trim((string) $overrideVoucherType);

        if ($overrideVoucherType !== '' && $overrideVoucherType !== 'Auto Select') {
            return $overrideVoucherType;
        }

        $nature = $transactionHead->nature;
        $headName = strtolower($transactionHead->name);
        $settlementCode = strtoupper($settlementType->code);
        $settlementName = strtolower($settlementType->name);

        if (
            $nature === 'Due'
            || $nature === 'Adjustment'
            || $settlementCode === 'DUE'
            || $settlementCode === 'ADVANCE_ADJUSTMENT'
            || $settlementName === 'due'
            || $settlementName === 'advance adjustment'
        ) {
            return 'Journal Voucher';
        }

        if ($nature === 'Receipt' || str_contains($headName, 'received')) {
            return 'Receipt Voucher';
        }

        if ($nature === 'Payment' || $nature === 'Advance' || str_contains($headName, 'paid')) {
            return 'Payment Voucher';
        }

        return 'Journal Voucher';
    }
}
