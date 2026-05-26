<?php

namespace App\AccountingEngine\Services;

use App\Models\VoucherAttachment;
use App\Models\VoucherHeader;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

class PostingService
{
    public function __construct(
        private readonly JournalPostingService $journalPostingService
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $preview
     * @param array<int, array<string, mixed>> $entries
     */
    public function createVoucher(
        array $data,
        array $preview,
        array $entries,
        int $companyId,
        int $financialYearId,
        string $voucherNumber,
        CarbonInterface $voucherDate,
        ?UploadedFile $attachment = null,
        ?int $userId = null
    ): VoucherHeader {
        $status = (string) ($data['status'] ?? VoucherHeader::STATUS_POSTED);
        $now = now();
        $lifecycleState = (string) ($data['lifecycle_state'] ?? match ($status) {
            VoucherHeader::STATUS_DRAFT => 'Draft',
            VoucherHeader::STATUS_PENDING_REVIEW => 'Submitted',
            VoucherHeader::STATUS_POSTED => 'Posted',
            VoucherHeader::STATUS_CANCELLED => 'Void',
            VoucherHeader::STATUS_REVERSED => 'Reversed',
            default => $status,
        });
        $totalDebit = round(collect($entries)->sum(fn (array $entry) => (float) ($entry['debit'] ?? 0)), 2);
        $totalCredit = round(collect($entries)->sum(fn (array $entry) => (float) ($entry['credit'] ?? 0)), 2);

        $voucherPayload = [
            'company_id' => $companyId ?: null,
            'financial_year_id' => $financialYearId,
            'voucher_number' => $voucherNumber,
            'voucher_type' => $preview['voucher_type'],
            'voucher_date' => $voucherDate->toDateString(),
            'transaction_head_id' => $data['transaction_head_id'],
            'settlement_type_id' => $data['settlement_type_id'],
            'party_id' => $data['party_id'] ?? null,
            'cash_bank_account_id' => $preview['cash_bank_account_id'] ?? null,
            'amount' => round((float) ($data['amount'] ?? 0), 2),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'party_ledger_effect' => $preview['party_ledger_effect'] ?? 'No Effect',
            'cash_bank_effect' => $preview['cash_bank_effect'] ?? 'No Cash/Bank',
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $status,
            'submitted_at' => $status === VoucherHeader::STATUS_DRAFT ? null : $now,
            'submitted_by' => $status === VoucherHeader::STATUS_DRAFT ? null : $userId,
            'approved_at' => $status === VoucherHeader::STATUS_POSTED ? ($data['approved_at'] ?? $now) : null,
            'approved_by' => $status === VoucherHeader::STATUS_POSTED ? ($data['approved_by'] ?? $userId) : null,
            'posted_at' => $status === VoucherHeader::STATUS_POSTED ? $now : null,
            'posted_by' => $status === VoucherHeader::STATUS_POSTED ? $userId : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        if (Schema::hasColumn('voucher_headers', 'lifecycle_state')) {
            $voucherPayload['lifecycle_state'] = $lifecycleState;
        }

        $voucher = VoucherHeader::query()->create($voucherPayload);

        foreach ($entries as $index => $entry) {
            $voucher->details()->create([
                'company_id' => $companyId ?: null,
                'branch_id' => $entry['branch_id'] ?? null,
                'transaction_date' => $voucherDate->toDateString(),
                'line_no' => $index + 1,
                'account_id' => $entry['account_id'],
                'party_id' => $entry['party_id'] ?? $data['party_id'] ?? null,
                'rule_line_id' => $entry['rule_line_id'] ?? null,
                'amount_source' => $entry['amount_source'] ?? 'transaction_amount',
                'entry_type' => $entry['entry_type'],
                'debit' => round((float) ($entry['debit'] ?? 0), 2),
                'credit' => round((float) ($entry['credit'] ?? 0), 2),
                'narration' => $entry['narration'] ?? $this->lineNarration($entry, $data, $preview),
            ]);
        }

        if ($status !== VoucherHeader::STATUS_DRAFT) {
            $this->journalPostingService->createOrSyncFromVoucher($voucher->fresh('details'), 'Transaction');
        }

        if ($attachment) {
            $this->storeAttachment($voucher, $attachment, $userId);
        }

        return $voucher->fresh(['details.account.accountType', 'details.party', 'attachments', 'journalHeader.lines.ledger.accountType', 'journalHeader.lines.party']);
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $data
     * @param array<string, mixed> $preview
     */
    public function lineNarration(array $entry, array $data, array $preview): string
    {
        $parts = array_filter([
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $preview['transaction_head'] ?? null,
            $preview['settlement_type'] ?? null,
            $entry['source_label'] ?? null,
        ]);

        return implode(' | ', array_unique($parts)) ?: 'Auto-posted transaction line';
    }

    private function storeAttachment(VoucherHeader $voucher, UploadedFile $attachment, ?int $userId): void
    {
        $path = $attachment->store('voucher-attachments', 'local');

        VoucherAttachment::query()->create([
            'voucher_header_id' => $voucher->id,
            'original_name' => $attachment->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $attachment->getMimeType(),
            'size_bytes' => $attachment->getSize(),
            'created_by' => $userId,
        ]);
    }
}
