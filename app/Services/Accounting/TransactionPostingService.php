<?php

namespace App\Services\Accounting;

use App\AccountingEngine\Services\AuditTrailService;
use App\AccountingEngine\Services\FinancialPeriodGuard;
use App\AccountingEngine\Services\JournalBuilder;
use App\AccountingEngine\Services\JournalValidator;
use App\AccountingEngine\Services\PartyRegisterService;
use App\AccountingEngine\Services\PostingService;
use App\AccountingEngine\Services\VoucherNumberService;
use App\Services\Approval\ApprovalWorkflowService;
use App\Models\CashBankAccount;
use App\Models\Company;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\VoucherHeader;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionPostingService
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly JournalValidator $journalValidator,
        private readonly FinancialPeriodGuard $financialPeriodGuard,
        private readonly TransactionVoucherTypeService $voucherTypeService,
        private readonly VoucherNumberService $voucherNumberService,
        private readonly PostingService $postingService,
        private readonly PartyRegisterService $partyRegisterService,
        private readonly AuditTrailService $auditTrailService,
        private readonly MappingResolverService $mappingResolver,
        private readonly ApprovalWorkflowService $approvalWorkflowService
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function preview(array $data, ?int $userId = null, bool $draft = false): array
    {
        $voucherDate = isset($data['voucher_date'])
            ? Carbon::parse($data['voucher_date'])
            : now();
        $companyId = $this->resolveCompanyId($data, $userId);
        $financialYear = $this->financialPeriodGuard->resolveOpenPeriod($companyId, $voucherDate);

        $transactionHead = TransactionHead::query()
            ->where('status', 'Active')
            ->findOrFail($data['transaction_head_id']);

        $settlementType = SettlementType::query()
            ->where('status', 'Active')
            ->findOrFail($data['settlement_type_id']);

        $amount = round((float) ($data['amount'] ?? 0), 2);

        $mapping = $this->journalBuilder->buildFromTransaction(
            transactionHeadId: (int) $data['transaction_head_id'],
            settlementTypeId: (int) $data['settlement_type_id'],
            amount: $amount,
            cashBankAccountId: $data['cash_bank_account_id'] ?? null,
            partyId: $data['party_id'] ?? null,
            companyId: $companyId,
            userId: $userId,
            voucherDate: $voucherDate->toDateString()
        );

        $entries = collect($mapping['entries'] ?? []);
        $cashBankRequired = $this->mappingResolver->requiresCashBank(
            (int) $data['transaction_head_id'],
            (int) $data['settlement_type_id'],
            $companyId
        );

        $this->journalValidator->assertValid(
            entries: $entries->all(),
            amount: $amount,
            partyId: $data['party_id'] ?? null,
            cashBankRequired: $cashBankRequired,
            cashBankAccountId: $data['cash_bank_account_id'] ?? null
        );

        $totalDebit = round((float) $entries->sum('debit'), 2);
        $totalCredit = round((float) $entries->sum('credit'), 2);

        $voucherType = $this->voucherTypeService->resolve(
            transactionHead: $transactionHead,
            settlementType: $settlementType,
            mappingRule: $mapping['rule'] ?? null,
            entries: $mapping['entries'] ?? [],
            draft: $draft
        );

        $voucherNumber = $this->voucherNumberService->preview(
            $voucherType,
            $financialYear,
            $voucherDate
        );

        $cashBankEffect = $this->cashBankEffect($mapping['cash_bank_account'] ?? null, $entries->all());

        return [
            'company_id' => $companyId,
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
            'cash_bank_account_id' => $mapping['cash_bank_account_id'] ?? (($mapping['cash_bank_account'] ?? null)?->id),
            'entries' => $entries->values()->all(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => $totalDebit === $totalCredit,
            'mapping_found' => true,
            'accounting_rule_id' => $mapping['accounting_rule_id'] ?? null,
            'accounting_rule_code' => $mapping['accounting_rule_code'] ?? null,
            'legacy_ledger_mapping_rule_id' => $mapping['legacy_ledger_mapping_rule_id'] ?? ($mapping['rule']->id ?? null),
            'accounting_principle' => 'Assets and Expenses normally increase by Debit. Liabilities, Equity, and Income normally increase by Credit. Opposite-side posting decreases the account balance.',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?UploadedFile $attachment = null, ?int $userId = null): VoucherHeader
    {
        return DB::transaction(function () use ($data, $attachment, $userId): VoucherHeader {
            $status = $data['status'] ?? VoucherHeader::STATUS_POSTED;
            $draft = $status === VoucherHeader::STATUS_DRAFT;

            if (! in_array($status, [VoucherHeader::STATUS_DRAFT, VoucherHeader::STATUS_POSTED, VoucherHeader::STATUS_PENDING_REVIEW], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Transaction status must be Draft, Submitted, or Posted.',
                ]);
            }

            $preview = $this->preview($data, $userId, $draft);
            $voucherDate = Carbon::parse($preview['voucher_date']);
            $companyId = (int) ($preview['company_id'] ?? $this->resolveCompanyId($data, $userId));
            $financialYear = $this->financialPeriodGuard->resolveOpenPeriod($companyId, $voucherDate);

            $voucherNumber = $this->voucherNumberService->reserveWithLock(
                $preview['voucher_type'],
                $financialYear,
                $voucherDate
            );

            $requiresApproval = $status === VoucherHeader::STATUS_POSTED
                && $this->approvalWorkflowService->shouldSubmitForApproval($data, (string) $preview['voucher_type'], $companyId);

            $data['status'] = $requiresApproval ? VoucherHeader::STATUS_PENDING_REVIEW : $status;
            $data['lifecycle_state'] = $requiresApproval
                ? 'Submitted'
                : ($status === VoucherHeader::STATUS_DRAFT ? 'Draft' : 'Posted');

            $voucher = $this->postingService->createVoucher(
                data: $data,
                preview: array_merge($preview, ['voucher_number' => $voucherNumber]),
                entries: $preview['entries'],
                companyId: $companyId,
                financialYearId: (int) $financialYear->id,
                voucherNumber: $voucherNumber,
                voucherDate: $voucherDate,
                attachment: $attachment,
                userId: $userId
            );

            if ($requiresApproval) {
                $this->approvalWorkflowService->markSubmitted($voucher, $userId);
                $this->auditTrailService->record($voucher, (int) $voucher->id, 'voucher_submitted', null, $voucher->toArray(), $userId);
            }

            if (!$requiresApproval && $status === VoucherHeader::STATUS_POSTED) {
                $this->partyRegisterService->recordIfNeeded($voucher, $preview);
                $this->auditTrailService->recordPostedVoucher($voucher, $userId);
            }

            return $voucher->fresh([
                'transactionHead',
                'settlementType',
                'party',
                'cashBankAccount',
                'details.account.accountType',
                'details.party',
                'attachments',
            ]);
        });
    }

    private function resolveCompanyId(array $data, ?int $userId = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);

        if ($companyId > 0) {
            return $companyId;
        }

        return (int) Company::query()->orderBy('id')->value('id');
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function cashBankEffect(?CashBankAccount $cashBankAccount, array $entries): string
    {
        if (! $cashBankAccount) {
            return 'No Cash/Bank';
        }

        $ledgerId = (int) $cashBankAccount->linked_ledger_account_id;

        foreach ($entries as $entry) {
            if ((int) ($entry['account_id'] ?? 0) !== $ledgerId) {
                continue;
            }

            $base = $cashBankAccount->type === 'Cash' ? 'Cash' : 'Bank';

            return ((float) ($entry['debit'] ?? 0)) > 0
                ? "{$base} In"
                : "{$base} Out";
        }

        return 'No Cash/Bank';
    }
}
