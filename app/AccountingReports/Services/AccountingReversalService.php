<?php

namespace App\AccountingReports\Services;

use App\AccountingEngine\Services\AuditTrailService;
use App\AccountingEngine\Services\JournalPostingService;
use App\Models\JournalHeader;
use App\Models\VoucherHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingReversalService
{
    public function __construct(
        private readonly JournalPostingService $journalPostingService,
        private readonly AuditTrailService $auditTrailService
    ) {
    }

    private array $postedStatuses = ['Posted', 'POSTED', 'posted'];
    private array $reversedStatuses = ['Reversed', 'REVERSED', 'reversed'];
    private array $cancelledStatuses = ['Cancelled', 'CANCELLED', 'cancelled'];

    public function reverseVoucher(int|string $voucherId, ?int $userId = null): object
    {
        $source = DB::table('voucher_headers')
            ->where('id', $voucherId)
            ->whereNull('deleted_at')
            ->first();

        if (! $source) {
            throw ValidationException::withMessages(['voucher' => 'Voucher not found.']);
        }

        if (in_array($source->status, array_merge($this->reversedStatuses, $this->cancelledStatuses), true)) {
            throw ValidationException::withMessages(['voucher' => 'This voucher is already reversed or cancelled.']);
        }

        if (! in_array($source->status, $this->postedStatuses, true)) {
            throw ValidationException::withMessages(['voucher' => 'Only posted vouchers can be reversed. Draft vouchers do not affect reports.']);
        }

        $sourceLines = DB::table('voucher_details')
            ->where('voucher_header_id', $source->id)
            ->orderBy('line_no')
            ->orderBy('id')
            ->get();

        if ($sourceLines->count() < 2) {
            throw ValidationException::withMessages(['voucher' => 'A voucher must have at least two debit/credit lines before it can be reversed.']);
        }

        $sourceDebit = $sourceLines->sum(fn ($line) => (float) $line->debit);
        $sourceCredit = $sourceLines->sum(fn ($line) => (float) $line->credit);

        if (round($sourceDebit, 2) !== round($sourceCredit, 2)) {
            throw ValidationException::withMessages(['voucher' => 'Source voucher is not balanced. Reversal is blocked.']);
        }

        return DB::transaction(function () use ($source, $sourceLines, $userId) {
            $today = now()->toDateString();
            $now = now();
            $reversalVoucherNo = $this->generateReversalVoucherNo($today);

            $newVoucherId = DB::table('voucher_headers')->insertGetId([
                'company_id' => $source->company_id,
                'financial_year_id' => $source->financial_year_id,
                'voucher_number' => $reversalVoucherNo,
                'voucher_type' => 'Reversal Voucher',
                'voucher_date' => $today,
                'transaction_head_id' => $source->transaction_head_id,
                'settlement_type_id' => $source->settlement_type_id,
                'party_id' => $source->party_id,
                'cash_bank_account_id' => $source->cash_bank_account_id,
                'amount' => $source->amount,
                'total_debit' => $source->total_credit,
                'total_credit' => $source->total_debit,
                'party_ledger_effect' => 'Reversal',
                'cash_bank_effect' => 'Reversal',
                'reference' => 'Reversal of ' . $source->voucher_number,
                'notes' => 'Reversal voucher generated for ' . $source->voucher_number,
                'status' => 'Posted',
                'submitted_at' => $now,
                'submitted_by' => $userId,
                'posted_at' => $now,
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $lineNo = 1;
            $debitTotal = 0.0;
            $creditTotal = 0.0;

            foreach ($sourceLines as $sourceLine) {
                $debit = (float) $sourceLine->credit;
                $credit = (float) $sourceLine->debit;

                if ($debit <= 0 && $credit <= 0) {
                    throw ValidationException::withMessages(['voucher' => 'Invalid zero-value voucher detail found.']);
                }

                DB::table('voucher_details')->insert([
                    'voucher_header_id' => $newVoucherId,
                    'company_id' => $source->company_id,
                    'branch_id' => $sourceLine->branch_id ?? null,
                    'transaction_date' => $today,
                    'line_no' => $lineNo++,
                    'account_id' => $sourceLine->account_id,
                    'party_id' => $sourceLine->party_id,
                    'rule_line_id' => $sourceLine->rule_line_id ?? null,
                    'amount_source' => $sourceLine->amount_source ?? 'reversal',
                    'entry_type' => $debit > 0 ? 'Debit' : 'Credit',
                    'debit' => $debit,
                    'credit' => $credit,
                    'narration' => 'Reversal of ' . $source->voucher_number,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $debitTotal += $debit;
                $creditTotal += $credit;
            }

            if (round($debitTotal, 2) !== round($creditTotal, 2)) {
                throw ValidationException::withMessages(['voucher' => 'Generated reversal voucher is not balanced.']);
            }

            $reversalVoucher = VoucherHeader::query()
                ->with('details')
                ->findOrFail($newVoucherId);

            $reversalJournal = $this->journalPostingService->createOrSyncFromVoucher($reversalVoucher, 'Reversal');

            DB::table('voucher_headers')
                ->where('id', $source->id)
                ->update([
                    'status' => 'Reversed',
                    'updated_by' => $userId,
                    'updated_at' => $now,
                ]);

            $sourceVoucher = VoucherHeader::query()
                ->with(['details.account', 'details.party'])
                ->find($source->id);

            if ($sourceVoucher) {
                $this->journalPostingService->markVoucherJournalStatus($sourceVoucher, JournalHeader::STATUS_REVERSED);
                $this->auditTrailService->recordVoucherReversal(
                    $sourceVoucher,
                    $reversalVoucher->fresh(['details.account', 'details.party']) ?? $reversalVoucher,
                    $userId,
                    ['debit_total' => $debitTotal, 'credit_total' => $creditTotal]
                );
            }

            return (object) [
                'voucher_id' => $newVoucherId,
                'voucher_no' => $reversalVoucherNo,
                'journal_id' => $reversalJournal?->id ?? $newVoucherId,
                'journal_no' => $reversalJournal?->journal_no ?? $reversalVoucherNo,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
            ];
        });
    }

    private function generateReversalVoucherNo(string $date): string
    {
        $prefix = (string) config('accounting_reports.reverse.voucher_prefix', 'REV');
        $year = substr($date, 0, 4);
        $count = DB::table('voucher_headers')
            ->where('voucher_number', 'LIKE', "$prefix-$year-%")
            ->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $count);
    }
}
