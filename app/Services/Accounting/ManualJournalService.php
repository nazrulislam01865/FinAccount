<?php

namespace App\Services\Accounting;

use App\AccountingEngine\Services\AuditTrailService;
use App\AccountingEngine\Services\FinancialPeriodGuard;
use App\AccountingEngine\Services\JournalPostingService;
use App\AccountingEngine\Services\JournalValidator;
use App\AccountingEngine\Services\VoucherNumberService;
use App\Models\Company;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\VoucherHeader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualJournalService
{
    public function __construct(
        private readonly FinancialPeriodGuard $financialPeriodGuard,
        private readonly VoucherNumberService $voucherNumberService,
        private readonly JournalValidator $journalValidator,
        private readonly JournalPostingService $journalPostingService,
        private readonly AuditTrailService $auditTrailService
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function post(array $data, ?int $userId = null): VoucherHeader
    {
        return DB::transaction(function () use ($data, $userId): VoucherHeader {
            $companyId = $this->resolveCompanyId($data);
            $journalDate = Carbon::parse($data['journal_date'] ?? now()->toDateString());
            $financialYear = $this->financialPeriodGuard->resolveOpenPeriod($companyId, $journalDate);
            $lines = $this->validateLines($data['lines'] ?? []);
            $status = (string) ($data['status'] ?? VoucherHeader::STATUS_POSTED);
            $voucherNumber = $this->voucherNumberService->reserveWithLock('Journal Voucher', $financialYear, $journalDate);
            $transactionHead = $this->manualJournalHead($companyId, $userId);
            $settlementType = $this->manualJournalSettlementType();
            $transactionHead->settlementTypes()->syncWithoutDetaching([$settlementType->id]);
            $now = now();
            $totalDebit = round(collect($lines)->sum('debit'), 2);
            $totalCredit = round(collect($lines)->sum('credit'), 2);

            $voucher = VoucherHeader::query()->create([
                'company_id' => $companyId ?: null,
                'financial_year_id' => $financialYear->id,
                'voucher_number' => $voucherNumber,
                'voucher_type' => 'Journal Voucher',
                'voucher_date' => $journalDate->toDateString(),
                'transaction_head_id' => $transactionHead->id,
                'settlement_type_id' => $settlementType->id,
                'party_id' => null,
                'cash_bank_account_id' => null,
                'amount' => $totalDebit,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'party_ledger_effect' => 'Manual Journal',
                'cash_bank_effect' => 'No Cash/Bank',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['narration'] ?? 'Manual journal entry',
                'status' => $status,
                'lifecycle_state' => $status === VoucherHeader::STATUS_DRAFT ? 'Draft' : 'Posted',
                'submitted_at' => $status === VoucherHeader::STATUS_DRAFT ? null : $now,
                'submitted_by' => $status === VoucherHeader::STATUS_DRAFT ? null : $userId,
                'approved_at' => $status === VoucherHeader::STATUS_POSTED ? $now : null,
                'approved_by' => $status === VoucherHeader::STATUS_POSTED ? $userId : null,
                'posted_at' => $status === VoucherHeader::STATUS_POSTED ? $now : null,
                'posted_by' => $status === VoucherHeader::STATUS_POSTED ? $userId : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($lines as $index => $line) {
                $voucher->details()->create([
                    'company_id' => $companyId ?: null,
                    'branch_id' => null,
                    'transaction_date' => $journalDate->toDateString(),
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'party_id' => $line['party_id'],
                    'rule_line_id' => null,
                    'amount_source' => 'manual_journal',
                    'entry_type' => $line['debit'] > 0 ? 'Debit' : 'Credit',
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'narration' => $line['narration'] ?: ($data['narration'] ?? 'Manual journal entry'),
                ]);
            }

            $voucher = $voucher->fresh(['details.account.accountType', 'details.party']);

            if ($status !== VoucherHeader::STATUS_DRAFT) {
                $this->journalPostingService->createOrSyncFromVoucher($voucher, 'Manual Journal');
                $this->auditTrailService->recordPostedVoucher($voucher, $userId);
            }

            return $voucher->fresh(['details.account.accountType', 'details.party', 'journalHeader.lines.ledger.accountType', 'journalHeader.lines.party']);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $rawLines
     * @return array<int, array<string, mixed>>
     */
    private function validateLines(array $rawLines): array
    {
        $lines = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach (array_values($rawLines) as $index => $rawLine) {
            $ledgerId = (int) ($rawLine['ledger_id'] ?? $rawLine['account_id'] ?? 0);
            $partyId = isset($rawLine['party_id']) && $rawLine['party_id'] !== null ? (int) $rawLine['party_id'] : null;
            $debit = round((float) ($rawLine['debit_amount'] ?? $rawLine['debit'] ?? 0), 2);
            $credit = round((float) ($rawLine['credit_amount'] ?? $rawLine['credit'] ?? 0), 2);

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    'lines' => 'A manual journal line cannot contain both debit and credit amounts.',
                ]);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Each manual journal line must contain either a debit or a credit amount.',
                ]);
            }

            $this->journalValidator->assertLedgerIsPostable($ledgerId, $index + 1, $partyId);

            $lines[] = [
                'account_id' => $ledgerId,
                'party_id' => $partyId,
                'debit' => $debit,
                'credit' => $credit,
                'narration' => $rawLine['line_narration'] ?? null,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'Manual Journal needs at least two debit/credit lines.',
            ]);
        }

        if (round($totalDebit, 2) <= 0 || round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw ValidationException::withMessages([
                'lines' => 'Debit and Credit totals must be equal before posting a manual journal.',
            ]);
        }

        return $lines;
    }

    private function resolveCompanyId(array $data): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);

        return $companyId > 0 ? $companyId : (int) Company::query()->orderBy('id')->value('id');
    }

    private function manualJournalHead(int $companyId, ?int $userId): TransactionHead
    {
        return TransactionHead::query()->firstOrCreate(
            [
                'company_id' => $companyId ?: null,
                'name' => 'Manual Journal',
            ],
            [
                'head_code' => 'MANUAL_JOURNAL',
                'nature' => 'Adjustment',
                'category' => 'Adjustment',
                'default_movement' => 'Increase',
                'payment_method_required' => false,
                'party_required_mode' => 'Optional',
                'transaction_screen' => 'Manual Journal',
                'is_system_default' => true,
                'is_user_selectable' => false,
                'requires_party' => false,
                'requires_reference' => false,
                'description' => 'Manual debit and credit adjustment entry for accountant/admin users.',
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function manualJournalSettlementType(): SettlementType
    {
        return SettlementType::query()->firstOrCreate(
            ['code' => 'JOURNAL'],
            [
                'name' => 'Journal',
                'status' => 'Active',
                'sort_order' => 90,
            ]
        );
    }
}
