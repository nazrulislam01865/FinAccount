<?php

namespace App\Http\Controllers\Approvals;

use App\Http\Controllers\Controller;
use App\Models\VoucherHeader;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalWorkflowService $approvalWorkflowService)
    {
    }

    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $pendingVouchers = VoucherHeader::query()
            ->with(['transactionHead', 'party', 'createdBy'])
            ->where('status', VoucherHeader::STATUS_PENDING_REVIEW)
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('approvals.index', [
            'pendingVouchers' => $pendingVouchers,
            'currency' => config('accounting_reports.currency', 'BDT'),
        ]);
    }

    public function approve(Request $request, VoucherHeader $voucher): RedirectResponse
    {
        $this->approvalWorkflowService->approveAndPost($voucher, $request->user(), $request->input('remarks'));

        return redirect()->route('approvals.index')->with('success', 'Transaction approved and posted successfully.');
    }

    public function reject(Request $request, VoucherHeader $voucher): RedirectResponse
    {
        $this->approvalWorkflowService->reject($voucher, $request->user(), $request->input('remarks'));

        return redirect()->route('approvals.index')->with('success', 'Transaction rejected successfully.');
    }
}
