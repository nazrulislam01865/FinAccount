<?php

namespace App\Services\Approval;

use App\AccountingEngine\Services\AuditTrailService;
use App\AccountingEngine\Services\PartyRegisterService;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Models\VoucherHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowService
{
    public function __construct(
        private readonly PartyRegisterService $partyRegisterService,
        private readonly AuditTrailService $auditTrailService
    ) {
    }

    public function workflowForVoucher(VoucherHeader $voucher): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::query()
            ->where('status', 'Active')
            ->where('approval_required', true)
            ->where(function ($query) use ($voucher) {
                $query->where('company_id', $voucher->company_id)
                    ->orWhereNull('company_id');
            })
            ->where(function ($query) use ($voucher) {
                $query->where('transaction_head_id', $voucher->transaction_head_id)
                    ->orWhereNull('transaction_head_id');
            })
            ->where(function ($query) use ($voucher) {
                $query->whereNull('transaction_type')
                    ->orWhere('transaction_type', $voucher->voucher_type)
                    ->orWhere('transaction_type', $voucher->transactionHead?->nature)
                    ->orWhere('transaction_type', $voucher->transactionHead?->category);
            })
            ->orderByRaw('CASE WHEN transaction_head_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN transaction_type IS NULL THEN 1 ELSE 0 END')
            ->orderBy('approval_level')
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function shouldSubmitForApproval(array $data, string $voucherType, int $companyId): bool
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $transactionHeadId = (int) ($data['transaction_head_id'] ?? 0);

        $workflow = ApprovalWorkflow::query()
            ->where('status', 'Active')
            ->where('approval_required', true)
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where(function ($query) use ($transactionHeadId) {
                $query->where('transaction_head_id', $transactionHeadId)->orWhereNull('transaction_head_id');
            })
            ->where(function ($query) use ($voucherType) {
                $query->whereNull('transaction_type')->orWhere('transaction_type', $voucherType);
            })
            ->orderByRaw('CASE WHEN transaction_head_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN transaction_type IS NULL THEN 1 ELSE 0 END')
            ->orderBy('approval_level')
            ->first();

        if (! $workflow) {
            return false;
        }

        if ($workflow->auto_approve_below_amount && $workflow->threshold_amount !== null) {
            return $amount >= (float) $workflow->threshold_amount;
        }

        return true;
    }

    public function markSubmitted(VoucherHeader $voucher, ?int $userId = null): void
    {
        $workflow = $this->workflowForVoucher($voucher);

        ApprovalLog::query()->create([
            'company_id' => $voucher->company_id,
            'approval_workflow_id' => $workflow?->id,
            'voucher_header_id' => $voucher->id,
            'approval_level' => $workflow?->approval_level ?? 1,
            'action' => 'Submitted',
            'remarks' => 'Transaction submitted for approval by workflow.',
            'acted_by' => $userId,
            'acted_at' => now(),
        ]);
    }

    public function approveAndPost(VoucherHeader $voucher, User $approver, ?string $remarks = null): VoucherHeader
    {
        if ($voucher->status !== VoucherHeader::STATUS_PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'voucher' => 'Only submitted transactions can be approved and posted.',
            ]);
        }

        $workflow = $this->workflowForVoucher($voucher);

        if ($workflow?->approver_role_id && ! $approver->roles()->where('roles.id', $workflow->approver_role_id)->exists() && ! $approver->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'permission' => 'Your role is not the assigned approver role for this workflow.',
            ]);
        }

        return DB::transaction(function () use ($voucher, $approver, $remarks, $workflow): VoucherHeader {
            $voucher->forceFill([
                'status' => VoucherHeader::STATUS_POSTED,
                'lifecycle_state' => 'Posted',
                'approved_at' => now(),
                'approved_by' => $approver->id,
                'posted_at' => now(),
                'posted_by' => $approver->id,
                'updated_by' => $approver->id,
            ])->save();

            ApprovalLog::query()->create([
                'company_id' => $voucher->company_id,
                'approval_workflow_id' => $workflow?->id,
                'voucher_header_id' => $voucher->id,
                'approval_level' => $workflow?->approval_level ?? 1,
                'action' => 'Approved',
                'remarks' => $remarks ?: 'Approved and posted.',
                'acted_by' => $approver->id,
                'acted_at' => now(),
            ]);

            $this->partyRegisterService->recordIfNeeded($voucher->fresh(['details.account.accountType']), [
                'party_ledger_effect' => $voucher->party_ledger_effect,
            ]);

            $this->auditTrailService->recordPostedVoucher($voucher->fresh(['details.account', 'details.party']), $approver->id);

            return $voucher->fresh(['transactionHead', 'party', 'details.account']);
        });
    }

    public function reject(VoucherHeader $voucher, User $approver, ?string $remarks = null): VoucherHeader
    {
        if ($voucher->status !== VoucherHeader::STATUS_PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'voucher' => 'Only submitted transactions can be rejected.',
            ]);
        }

        $workflow = $this->workflowForVoucher($voucher);

        return DB::transaction(function () use ($voucher, $approver, $remarks, $workflow): VoucherHeader {
            $voucher->forceFill([
                'status' => VoucherHeader::STATUS_CANCELLED,
                'lifecycle_state' => 'Rejected',
                'voided_at' => now(),
                'voided_by' => $approver->id,
                'void_reason' => $remarks ?: 'Rejected by approver.',
                'updated_by' => $approver->id,
            ])->save();

            ApprovalLog::query()->create([
                'company_id' => $voucher->company_id,
                'approval_workflow_id' => $workflow?->id,
                'voucher_header_id' => $voucher->id,
                'approval_level' => $workflow?->approval_level ?? 1,
                'action' => 'Rejected',
                'remarks' => $remarks ?: 'Rejected.',
                'acted_by' => $approver->id,
                'acted_at' => now(),
            ]);

            $this->auditTrailService->record($voucher, (int) $voucher->id, 'voucher_rejected', null, $voucher->fresh()->toArray(), $approver->id);

            return $voucher->fresh(['transactionHead', 'party']);
        });
    }
}
