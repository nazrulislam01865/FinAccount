<?php

namespace App\AccountingEngine\Services;

use App\Models\JournalHeader;
use App\Models\VoucherHeader;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    public function createOrSyncFromVoucher(VoucherHeader $voucher, string $sourceType = 'Voucher'): ?JournalHeader
    {
        if (! Schema::hasTable('journal_headers') || ! Schema::hasTable('journal_lines')) {
            return null;
        }

        $voucher->loadMissing(['details', 'transactionHead', 'party']);

        $details = $voucher->details;
        if ($details->count() < 2) {
            throw ValidationException::withMessages([
                'journal' => 'A posted journal must contain at least two debit/credit lines.',
            ]);
        }

        $totalDebit = round((float) $details->sum('debit'), 2);
        $totalCredit = round((float) $details->sum('credit'), 2);

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'journal' => 'Journal debit and credit totals must be equal before posting.',
            ]);
        }

        $journal = JournalHeader::query()->updateOrCreate(
            ['voucher_header_id' => $voucher->id],
            [
                'company_id' => $voucher->company_id,
                'financial_year_id' => $voucher->financial_year_id,
                'journal_no' => $this->generateJournalNo($voucher),
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
                'source_type' => $sourceType,
                'journal_date' => $voucher->voucher_date?->toDateString() ?? now()->toDateString(),
                'transaction_head_id' => $voucher->transaction_head_id,
                'party_id' => $voucher->party_id,
                'amount' => $voucher->amount,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => $this->journalStatusFromVoucher((string) $voucher->status),
                'narration' => $voucher->notes,
                'created_by' => $voucher->created_by,
                'submitted_by' => $voucher->submitted_by,
                'approved_by' => $voucher->approved_by,
                'posted_by' => $voucher->posted_by,
                'submitted_at' => $voucher->submitted_at,
                'approved_at' => $voucher->approved_at,
                'posted_at' => $voucher->posted_at,
            ]
        );

        $journal->lines()->delete();

        foreach ($details->sortBy(['line_no', 'id'])->values() as $index => $detail) {
            $debit = round((float) $detail->debit, 2);
            $credit = round((float) $detail->credit, 2);

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw ValidationException::withMessages([
                    'journal' => 'Each journal line must contain either debit or credit amount, not both and not zero.',
                ]);
            }

            $journal->lines()->create([
                'voucher_detail_id' => $detail->id,
                'line_no' => (int) ($detail->line_no ?: $index + 1),
                'ledger_id' => $detail->account_id,
                'party_id' => $detail->party_id,
                'branch_id' => $detail->branch_id,
                'rule_line_id' => $detail->rule_line_id,
                'amount_source' => $detail->amount_source ?: 'transaction_amount',
                'entry_type' => $detail->entry_type,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'line_narration' => $detail->narration,
            ]);
        }

        return $journal->fresh(['lines.ledger.accountType', 'lines.party', 'voucherHeader']);
    }

    public function markVoucherJournalStatus(VoucherHeader $voucher, string $status): void
    {
        if (! Schema::hasTable('journal_headers')) {
            return;
        }

        $voucher->journalHeader()?->update(['status' => $status]);
    }

    private function generateJournalNo(VoucherHeader $voucher): string
    {
        $base = 'JE-' . (string) $voucher->voucher_number;

        $query = JournalHeader::query()->where('journal_no', $base);
        if ($voucher->exists) {
            $query->where('voucher_header_id', '!=', $voucher->id);
        }

        if (! $query->exists()) {
            return $base;
        }

        return $base . '-' . $voucher->id;
    }

    private function journalStatusFromVoucher(string $voucherStatus): string
    {
        return match ($voucherStatus) {
            VoucherHeader::STATUS_POSTED => JournalHeader::STATUS_POSTED,
            VoucherHeader::STATUS_PENDING_REVIEW => JournalHeader::STATUS_SUBMITTED,
            VoucherHeader::STATUS_CANCELLED => JournalHeader::STATUS_CANCELLED,
            VoucherHeader::STATUS_REVERSED => JournalHeader::STATUS_REVERSED,
            default => JournalHeader::STATUS_DRAFT,
        };
    }
}
