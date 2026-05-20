<?php

namespace Database\Seeders;

use App\Models\AdvanceRegister;
use App\Models\DueRegister;
use App\Models\VoucherHeader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class AccountingRegisterBackfillSeeder extends Seeder
{
    public function run(): void
    {
        VoucherHeader::query()
            ->with(['details.account.accountType'])
            ->where('status', VoucherHeader::STATUS_POSTED)
            ->whereNotNull('party_id')
            ->orderBy('id')
            ->chunk(100, function (Collection $vouchers): void {
                foreach ($vouchers as $voucher) {
                    $this->backfillVoucher($voucher);
                }
            });
    }

    private function backfillVoucher(VoucherHeader $voucher): void
    {
        $effect = (string) ($voucher->party_ledger_effect ?: 'No Effect');
        $amount = round((float) $voucher->amount, 2);
        $partyId = (int) $voucher->party_id;
        $entries = $voucher->details;

        if ($amount <= 0 || $partyId <= 0 || $entries->isEmpty()) {
            return;
        }

        match ($effect) {
            'Increase Liability' => $this->upsertDue(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
                'Payable',
                'Increase',
                $amount
            ),
            'Decrease Liability' => $this->upsertDue(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
                'Payable',
                'Decrease',
                $amount
            ),
            'Increase Receivable' => $this->upsertDue(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
                'Receivable',
                'Increase',
                $amount
            ),
            'Decrease Receivable' => $this->upsertDue(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
                'Receivable',
                'Decrease',
                $amount
            ),
            'Increase Asset',
            'Increase Advance Asset' => $this->upsertAdvance(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
                'Paid',
                'Increase',
                $amount
            ),
            'Decrease Asset',
            'Decrease Advance Asset' => $this->backfillAdvancePaidAdjustment($voucher, $partyId, $entries, $amount),
            'Increase Advance Liability' => $this->upsertAdvance(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
                'Received',
                'Increase',
                $amount
            ),
            'Decrease Advance Liability' => $this->backfillAdvanceReceivedAdjustment($voucher, $partyId, $entries, $amount),
            default => null,
        };
    }

    private function backfillAdvancePaidAdjustment(VoucherHeader $voucher, int $partyId, Collection $entries, float $amount): void
    {
        $this->upsertAdvance(
            $voucher,
            $partyId,
            $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
            'Paid',
            'Decrease',
            $amount
        );

        $payableEntry = $entries->first(fn ($entry) =>
            $entry->entry_type === 'Debit'
            && $entry->account?->accountType?->name === 'Liability'
        );

        $this->upsertDue(
            $voucher,
            $partyId,
            $this->accountIdFromEntry($payableEntry),
            'Payable',
            'Decrease',
            $amount
        );
    }

    private function backfillAdvanceReceivedAdjustment(VoucherHeader $voucher, int $partyId, Collection $entries, float $amount): void
    {
        $this->upsertAdvance(
            $voucher,
            $partyId,
            $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
            'Received',
            'Decrease',
            $amount
        );

        $receivableEntry = $entries->first(fn ($entry) =>
            $entry->entry_type === 'Credit'
            && $entry->account?->accountType?->name === 'Asset'
        );

        $this->upsertDue(
            $voucher,
            $partyId,
            $this->accountIdFromEntry($receivableEntry),
            'Receivable',
            'Decrease',
            $amount
        );
    }

    private function upsertDue(VoucherHeader $voucher, int $partyId, ?int $accountId, string $dueType, string $movement, float $amount): void
    {
        if (!$accountId) {
            return;
        }

        DueRegister::query()->firstOrCreate(
            [
                'voucher_header_id' => $voucher->id,
                'party_id' => $partyId,
                'account_id' => $accountId,
                'due_type' => $dueType,
                'movement' => $movement,
            ],
            [
                'amount' => $amount,
                'balance_effect' => $movement === 'Increase' ? $amount : -1 * $amount,
                'status' => 'Open',
                'due_date' => $voucher->voucher_date,
            ]
        );
    }

    private function upsertAdvance(VoucherHeader $voucher, int $partyId, ?int $accountId, string $advanceType, string $movement, float $amount): void
    {
        if (!$accountId) {
            return;
        }

        AdvanceRegister::query()->firstOrCreate(
            [
                'voucher_header_id' => $voucher->id,
                'party_id' => $partyId,
                'account_id' => $accountId,
                'advance_type' => $advanceType,
                'movement' => $movement,
            ],
            [
                'amount' => $amount,
                'balance_effect' => $movement === 'Increase' ? $amount : -1 * $amount,
                'status' => 'Open',
            ]
        );
    }

    private function accountIdFromEntry($entry): ?int
    {
        if (!$entry || empty($entry->account_id)) {
            return null;
        }

        return (int) $entry->account_id;
    }
}
