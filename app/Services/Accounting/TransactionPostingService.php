<?php

namespace App\Services\Accounting;

use App\Models\AdvanceRegister;
use App\Models\AuditLog;
use App\Models\CashBankAccount;
use App\Models\Company;
use App\Models\DueRegister;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\VoucherAttachment;
use App\Models\VoucherHeader;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionPostingService
{
    public function __construct(
        private readonly FinancialYearService $financialYearService,
        private readonly MappingResolverService $mappingResolver,
        private readonly TransactionVoucherTypeService $voucherTypeService,
        private readonly VoucherNumberGeneratorService $voucherNumberGenerator
    ) {
    }

    public function preview(array $data, ?int $userId = null, bool $draft = false): array
    {
        $financialYear = $this->financialYearService->current($userId);

        if (!$financialYear) {
            throw ValidationException::withMessages([
                'financial_year_id' => 'Current Financial Year is not configured.',
            ]);
        }

        $voucherDate = isset($data['voucher_date'])
            ? Carbon::parse($data['voucher_date'])
            : now();

        $this->validateVoucherDateInsideFinancialYear($voucherDate, $financialYear);

        $transactionHead = TransactionHead::query()
            ->where('status', 'Active')
            ->findOrFail($data['transaction_head_id']);

        $settlementType = SettlementType::query()
            ->where('status', 'Active')
            ->findOrFail($data['settlement_type_id']);

        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $mapping = $this->mappingResolver->preview(
            transactionHeadId: (int) $data['transaction_head_id'],
            settlementTypeId: (int) $data['settlement_type_id'],
            amount: $amount,
            cashBankAccountId: $data['cash_bank_account_id'] ?? null,
            partyId: $data['party_id'] ?? null
        );

        $entries = collect($mapping['entries']);
        $totalDebit = round((float) $entries->sum('debit'), 2);
        $totalCredit = round((float) $entries->sum('credit'), 2);

        if ($entries->count() < 2) {
            throw ValidationException::withMessages([
                'ledger_preview' => 'At least two accounting lines are required for posting.',
            ]);
        }

        if ($totalDebit <= 0 || $totalCredit <= 0 || $totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'ledger_preview' => 'Debit and Credit totals must be equal before posting.',
            ]);
        }

        $voucherType = $this->voucherTypeService->resolve(
            $transactionHead,
            $settlementType,
            $data['voucher_type'] ?? null,
            $draft
        );

        $voucherNumber = $this->voucherNumberGenerator->preview(
            $voucherType,
            $financialYear,
            $voucherDate
        );

        $cashBankEffect = $this->cashBankEffect($mapping['cash_bank_account'], $mapping['entries']);

        return [
            'financial_year_id' => $financialYear->id,
            'financial_year_name' => $financialYear->display_name,
            'voucher_type' => $voucherType,
            'voucher_number' => $voucherNumber,
            'voucher_date' => $voucherDate->toDateString(),
            'transaction_head' => $transactionHead->name,
            'nature' => $transactionHead->nature,
            'settlement_type' => $settlementType->name,
            'party_ledger_effect' => $mapping['party_ledger_effect'] ?? 'No Effect',
            'cash_bank_effect' => $cashBankEffect,
            'entries' => $mapping['entries'],
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => $totalDebit === $totalCredit,
            'mapping_found' => true,
            'accounting_principle' => 'Assets and Expenses normally increase by Debit. Liabilities, Equity, and Income normally increase by Credit. Opposite-side posting decreases the account balance.',
        ];
    }

    public function save(array $data, ?UploadedFile $attachment = null, ?int $userId = null): VoucherHeader
    {
        return DB::transaction(function () use ($data, $attachment, $userId) {
            $status = $data['status'] ?? VoucherHeader::STATUS_POSTED;
            $draft = $status === VoucherHeader::STATUS_DRAFT;

            if (!in_array($status, [VoucherHeader::STATUS_DRAFT, VoucherHeader::STATUS_POSTED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Transaction status must be Draft or Posted.',
                ]);
            }

            $preview = $this->preview($data, $userId, $draft);

            $financialYear = $this->financialYearService->current($userId);

            if (!$financialYear) {
                throw ValidationException::withMessages([
                    'financial_year_id' => 'Current Financial Year is not configured.',
                ]);
            }

            $company = Company::query()->first();
            $voucherDate = Carbon::parse($data['voucher_date']);

            $voucherNumber = $this->voucherNumberGenerator->reserve(
                $preview['voucher_type'],
                $financialYear,
                $voucherDate
            );

            $voucher = VoucherHeader::query()->create([
                'company_id' => $company?->id,
                'financial_year_id' => $financialYear->id,
                'voucher_number' => $voucherNumber,
                'voucher_type' => $preview['voucher_type'],
                'voucher_date' => $voucherDate->toDateString(),
                'transaction_head_id' => $data['transaction_head_id'],
                'settlement_type_id' => $data['settlement_type_id'],
                'party_id' => $data['party_id'] ?? null,
                'cash_bank_account_id' => $data['cash_bank_account_id'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'total_debit' => $preview['total_debit'],
                'total_credit' => $preview['total_credit'],
                'party_ledger_effect' => $preview['party_ledger_effect'],
                'cash_bank_effect' => $preview['cash_bank_effect'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'posted_at' => $status === VoucherHeader::STATUS_POSTED ? now() : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($preview['entries'] as $index => $entry) {
                $voucher->details()->create([
                    'line_no' => $index + 1,
                    'account_id' => $entry['account_id'],
                    'party_id' => $data['party_id'] ?? null,
                    'entry_type' => $entry['entry_type'],
                    'debit' => round((float) $entry['debit'], 2),
                    'credit' => round((float) $entry['credit'], 2),
                    'narration' => $this->lineNarration($entry, $data, $preview),
                ]);
            }

            if ($status === VoucherHeader::STATUS_POSTED) {
                $this->createPartyRegisterMovement($voucher, $data, $preview);
            }

            if ($attachment) {
                $this->storeAttachment($voucher, $attachment, $userId);
            }

            AuditLog::query()->create([
                'auditable_type' => VoucherHeader::class,
                'auditable_id' => $voucher->id,
                'event' => $status === VoucherHeader::STATUS_POSTED ? 'posted' : 'draft_saved',
                'old_values' => null,
                'new_values' => $voucher->load(['details.account.accountType'])->toArray(),
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            return $voucher->fresh([
                'details.account.accountType',
                'party.partyType',
                'transactionHead',
                'settlementType',
                'cashBankAccount.linkedLedger.accountType',
            ]);
        });
    }

    private function createPartyRegisterMovement(
        VoucherHeader $voucher,
        array $data,
        array $preview
    ): void {
        $partyId = $data['party_id'] ?? null;

        if (!$partyId) {
            return;
        }

        $effect = $preview['party_ledger_effect'] ?? 'No Effect';
        $amount = round((float) $data['amount'], 2);
        $entries = collect($preview['entries']);

        match ($effect) {
            'Increase Liability' => $this->createDueMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
                'Payable',
                'Increase',
                $amount
            ),

            'Decrease Liability' => $this->createDueMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
                'Payable',
                'Decrease',
                $amount
            ),

            'Increase Receivable' => $this->createDueMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
                'Receivable',
                'Increase',
                $amount
            ),

            'Decrease Receivable' => $this->createDueMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
                'Receivable',
                'Decrease',
                $amount
            ),

            'Increase Asset',
            'Increase Advance Asset' => $this->createAdvanceMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
                'Paid',
                'Increase',
                $amount
            ),

            'Decrease Asset',
            'Decrease Advance Asset' => $this->createAdvanceMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
                'Paid',
                'Decrease',
                $amount
            ),

            'Increase Advance Liability' => $this->createAdvanceMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Credit')),
                'Received',
                'Increase',
                $amount
            ),

            'Decrease Advance Liability' => $this->createAdvanceMovement(
                $voucher,
                $partyId,
                $this->accountIdFromEntry($entries->firstWhere('entry_type', 'Debit')),
                'Received',
                'Decrease',
                $amount
            ),

            default => null,
        };
    }

    private function createDueMovement(
        VoucherHeader $voucher,
        int $partyId,
        ?int $accountId,
        string $dueType,
        string $movement,
        float $amount
    ): void {
        if (!$accountId) {
            return;
        }

        DueRegister::query()->create([
            'voucher_header_id' => $voucher->id,
            'party_id' => $partyId,
            'account_id' => $accountId,
            'due_type' => $dueType,
            'movement' => $movement,
            'amount' => $amount,
            'balance_effect' => $movement === 'Increase' ? $amount : -1 * $amount,
            'status' => 'Open',
        ]);
    }

    private function createAdvanceMovement(
        VoucherHeader $voucher,
        int $partyId,
        ?int $accountId,
        string $advanceType,
        string $movement,
        float $amount
    ): void {
        if (!$accountId) {
            return;
        }

        AdvanceRegister::query()->create([
            'voucher_header_id' => $voucher->id,
            'party_id' => $partyId,
            'account_id' => $accountId,
            'advance_type' => $advanceType,
            'movement' => $movement,
            'amount' => $amount,
            'balance_effect' => $movement === 'Increase' ? $amount : -1 * $amount,
            'status' => 'Open',
        ]);
    }

    private function storeAttachment(
        VoucherHeader $voucher,
        UploadedFile $attachment,
        ?int $userId
    ): void {
        $path = $attachment->storeAs(
            'voucher-attachments/' . $voucher->id,
            Str::uuid() . '.' . $attachment->getClientOriginalExtension(),
            'public'
        );

        VoucherAttachment::query()->create([
            'voucher_header_id' => $voucher->id,
            'original_name' => $attachment->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $attachment->getMimeType(),
            'size_bytes' => $attachment->getSize(),
            'created_by' => $userId,
        ]);
    }

    private function cashBankEffect(?CashBankAccount $cashBankAccount, array $entries): string
    {
        if (!$cashBankAccount) {
            return 'No Cash/Bank';
        }

        $ledgerId = (int) $cashBankAccount->linked_ledger_account_id;

        foreach ($entries as $entry) {
            if ((int) $entry['account_id'] !== $ledgerId) {
                continue;
            }

            $base = $cashBankAccount->type === 'Cash' ? 'Cash' : 'Bank';

            return ((float) $entry['debit']) > 0
                ? "{$base} In"
                : "{$base} Out";
        }

        return 'No Cash/Bank';
    }

    private function lineNarration(array $entry, array $data, array $preview): string
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

    private function accountIdFromEntry(?array $entry): ?int
    {
        if (!$entry || empty($entry['account_id'])) {
            return null;
        }

        return (int) $entry['account_id'];
    }

    private function validateVoucherDateInsideFinancialYear(Carbon $voucherDate, $financialYear): void
    {
        if ($voucherDate->lt($financialYear->start_date) || $voucherDate->gt($financialYear->end_date)) {
            throw ValidationException::withMessages([
                'voucher_date' => 'Transaction date must be inside the current Financial Year.',
            ]);
        }
    }
}