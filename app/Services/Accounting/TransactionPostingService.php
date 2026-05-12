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

        $transactionHead = TransactionHead::query()->findOrFail($data['transaction_head_id']);
        $settlementType = SettlementType::query()->findOrFail($data['settlement_type_id']);

        $amount = round((float) $data['amount'], 2);

        $mapping = $this->mappingResolver->preview(
            (int) $data['transaction_head_id'],
            (int) $data['settlement_type_id'],
            $amount,
            $data['cash_bank_account_id'] ?? null
        );

        $entries = collect($mapping['entries']);
        $totalDebit = round((float) $entries->sum('debit'), 2);
        $totalCredit = round((float) $entries->sum('credit'), 2);

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'ledger_preview' => 'Debit and Credit are not balanced. Posting is blocked.',
            ]);
        }

        $voucherType = $this->voucherTypeService->resolve(
            $transactionHead,
            $settlementType,
            $data['voucher_type'] ?? null,
            $draft
        );

        $voucherDate = isset($data['voucher_date'])
            ? Carbon::parse($data['voucher_date'])
            : now();

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
            'transaction_head' => $transactionHead->name,
            'nature' => $transactionHead->nature,
            'settlement_type' => $settlementType->name,
            'party_ledger_effect' => $mapping['rule']->party_ledger_effect,
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

            $preview = $this->preview($data, $userId, $draft);

            $financialYear = $this->financialYearService->current($userId);
            $company = Company::query()->first();

            $voucherNumber = $this->voucherNumberGenerator->reserve(
                $preview['voucher_type'],
                $financialYear,
                Carbon::parse($data['voucher_date'])
            );

            $voucher = VoucherHeader::query()->create([
                'company_id' => $company?->id,
                'financial_year_id' => $financialYear->id,
                'voucher_number' => $voucherNumber,
                'voucher_type' => $preview['voucher_type'],
                'voucher_date' => $data['voucher_date'],
                'transaction_head_id' => $data['transaction_head_id'],
                'settlement_type_id' => $data['settlement_type_id'],
                'party_id' => $data['party_id'] ?? null,
                'cash_bank_account_id' => $data['cash_bank_account_id'] ?? null,
                'amount' => $data['amount'],
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
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'narration' => $data['reference'] ?? $preview['transaction_head'],
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
                'new_values' => $voucher->toArray(),
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            return $voucher->fresh([
                'details.account',
                'party',
                'transactionHead',
                'settlementType',
                'cashBankAccount',
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

        $effect = $preview['party_ledger_effect'];
        $amount = round((float) $data['amount'], 2);
        $entries = collect($preview['entries']);

        match ($effect) {
            'Increase Liability' => $this->createDueMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Credit')['account_id'], 'Payable', 'Increase', $amount),
            'Decrease Liability' => $this->createDueMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Debit')['account_id'], 'Payable', 'Decrease', $amount),

            'Increase Receivable' => $this->createDueMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Debit')['account_id'], 'Receivable', 'Increase', $amount),
            'Decrease Receivable' => $this->createDueMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Credit')['account_id'], 'Receivable', 'Decrease', $amount),

            'Increase Advance Asset' => $this->createAdvanceMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Debit')['account_id'], 'Paid', 'Increase', $amount),
            'Decrease Advance Asset' => $this->createAdvanceMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Credit')['account_id'], 'Paid', 'Decrease', $amount),

            'Increase Advance Liability' => $this->createAdvanceMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Credit')['account_id'], 'Received', 'Increase', $amount),
            'Decrease Advance Liability' => $this->createAdvanceMovement($voucher, $partyId, $entries->firstWhere('entry_type', 'Debit')['account_id'], 'Received', 'Decrease', $amount),

            default => null,
        };
    }

    private function createDueMovement(
        VoucherHeader $voucher,
        int $partyId,
        int $accountId,
        string $dueType,
        string $movement,
        float $amount
    ): void {
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
        int $accountId,
        string $advanceType,
        string $movement,
        float $amount
    ): void {
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
}
