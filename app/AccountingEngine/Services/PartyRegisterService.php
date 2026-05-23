<?php

namespace App\AccountingEngine\Services;

use App\Models\AdvanceRegister;
use App\Models\ChartOfAccount;
use App\Models\DueRegister;
use App\Models\VoucherDetail;
use App\Models\VoucherHeader;
use Illuminate\Support\Collection;

class PartyRegisterService
{
    public function recordIfNeeded(VoucherHeader $voucher, array $preview = []): void
    {
        $voucher->loadMissing(['details.account.accountType']);
        $partyId = (int) ($voucher->party_id ?: 0);

        if ($partyId <= 0) {
            return;
        }

        $effect = (string) ($preview['party_ledger_effect'] ?? $voucher->party_ledger_effect ?? 'No Effect');
        $amount = round((float) $voucher->amount, 2);
        $details = $voucher->details;

        match ($effect) {
            'Increase Liability' => $this->createDueMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Credit'),
                $partyId,
                'Payable',
                'Increase',
                $amount
            ),
            'Decrease Liability' => $this->createDueMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Debit'),
                $partyId,
                'Payable',
                'Decrease',
                $amount
            ),
            'Increase Receivable' => $this->createDueMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Debit'),
                $partyId,
                'Receivable',
                'Increase',
                $amount
            ),
            'Decrease Receivable' => $this->createDueMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Credit'),
                $partyId,
                'Receivable',
                'Decrease',
                $amount
            ),
            'Increase Asset', 'Increase Advance Asset' => $this->createAdvanceMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Debit'),
                $partyId,
                'Paid',
                'Increase',
                $amount
            ),
            'Decrease Asset', 'Decrease Advance Asset' => $this->createAdvanceMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Credit'),
                $partyId,
                'Paid',
                'Decrease',
                $amount
            ),
            'Increase Advance Liability' => $this->createAdvanceMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Credit'),
                $partyId,
                'Received',
                'Increase',
                $amount
            ),
            'Decrease Advance Liability' => $this->createAdvanceMovementFromDetail(
                $details->first(fn (VoucherDetail $detail) => $detail->entry_type === 'Debit'),
                $partyId,
                'Received',
                'Decrease',
                $amount
            ),
            default => null,
        };

        $this->recordLinkedDueForAdvanceAdjustment($details, $partyId, $effect, $amount);
    }

    public function recordOpeningBalance(VoucherHeader $voucher): void
    {
        $voucher->loadMissing(['details.account.accountType']);

        foreach ($voucher->details as $detail) {
            $partyId = (int) ($detail->party_id ?: $voucher->party_id ?: 0);

            if ($partyId <= 0 || ! $detail->account) {
                continue;
            }

            $ledger = $detail->account;
            $ledgerType = strtolower((string) $ledger->ledger_type . ' ' . $ledger->account_name);
            $amount = round(max((float) $detail->debit, (float) $detail->credit), 2);

            if ($amount <= 0) {
                continue;
            }

            if ($this->looksLikeReceivable($ledger)) {
                $this->createDueMovementFromDetail($detail, $partyId, 'Receivable', $detail->debit > 0 ? 'Increase' : 'Decrease', $amount);
                continue;
            }

            if ($this->looksLikePayable($ledger)) {
                $this->createDueMovementFromDetail($detail, $partyId, 'Payable', $detail->credit > 0 ? 'Increase' : 'Decrease', $amount);
                continue;
            }

            if (str_contains($ledgerType, 'advance paid')) {
                $this->createAdvanceMovementFromDetail($detail, $partyId, 'Paid', $detail->debit > 0 ? 'Increase' : 'Decrease', $amount);
                continue;
            }

            if (str_contains($ledgerType, 'advance received')) {
                $this->createAdvanceMovementFromDetail($detail, $partyId, 'Received', $detail->credit > 0 ? 'Increase' : 'Decrease', $amount);
            }
        }
    }

    private function recordLinkedDueForAdvanceAdjustment(Collection $details, int $partyId, string $effect, float $amount): void
    {
        if ($effect === 'Decrease Advance Asset') {
            $payableDetail = $details->first(fn (VoucherDetail $detail): bool =>
                $detail->entry_type === 'Debit'
                && $detail->account?->accountType?->name === 'Liability'
            );

            $this->createDueMovementFromDetail($payableDetail, $partyId, 'Payable', 'Decrease', $amount);
            return;
        }

        if ($effect === 'Decrease Advance Liability') {
            $receivableDetail = $details->first(fn (VoucherDetail $detail): bool =>
                $detail->entry_type === 'Credit'
                && $detail->account?->accountType?->name === 'Asset'
            );

            $this->createDueMovementFromDetail($receivableDetail, $partyId, 'Receivable', 'Decrease', $amount);
        }
    }

    private function createDueMovementFromDetail(?VoucherDetail $detail, int $partyId, string $dueType, string $movement, float $amount): void
    {
        if (! $detail || ! $detail->account_id || $amount <= 0) {
            return;
        }

        DueRegister::query()->create([
            'voucher_header_id' => $detail->voucher_header_id,
            'voucher_detail_id' => $detail->id,
            'source_voucher_detail_id' => $detail->id,
            'party_id' => $partyId,
            'account_id' => $detail->account_id,
            'due_type' => $dueType,
            'movement' => $movement,
            'amount' => $amount,
            'balance_effect' => $movement === 'Increase' ? $amount : -1 * $amount,
            'status' => 'Open',
        ]);
    }

    private function createAdvanceMovementFromDetail(?VoucherDetail $detail, int $partyId, string $advanceType, string $movement, float $amount): void
    {
        if (! $detail || ! $detail->account_id || $amount <= 0) {
            return;
        }

        AdvanceRegister::query()->create([
            'voucher_header_id' => $detail->voucher_header_id,
            'voucher_detail_id' => $detail->id,
            'source_voucher_detail_id' => $detail->id,
            'party_id' => $partyId,
            'account_id' => $detail->account_id,
            'advance_type' => $advanceType,
            'movement' => $movement,
            'amount' => $amount,
            'balance_effect' => $movement === 'Increase' ? $amount : -1 * $amount,
            'status' => 'Open',
        ]);
    }

    private function looksLikeReceivable(ChartOfAccount $ledger): bool
    {
        $name = strtolower($ledger->ledger_type . ' ' . $ledger->account_name);

        return str_contains($name, 'receivable') || str_contains($name, 'customer due');
    }

    private function looksLikePayable(ChartOfAccount $ledger): bool
    {
        $name = strtolower($ledger->ledger_type . ' ' . $ledger->account_name);

        return str_contains($name, 'payable') || str_contains($name, 'supplier due');
    }
}
