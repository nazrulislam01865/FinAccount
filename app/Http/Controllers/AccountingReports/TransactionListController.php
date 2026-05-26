<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\AccountingReports\Services\AccountingReversalService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\TransactionReportRequest;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Models\VoucherHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\Reports\NativeReportExportService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TransactionListController extends Controller
{
    public function __construct(
        private readonly AccountingReportService $reports,
        private readonly AccountingReversalService $reversal,
    ) {
    }

    public function index(TransactionReportRequest $request): Response
    {
        $filters = $request->filters();
        $filters['company_id'] = (int) ($request->user()?->company_id ?? 0);
        $data = $this->reports->paginateTransactions($filters);

        return response()->view('accounting_reports.transactions.index', [
            'filters' => $filters,
            'transactions' => $data['transactions'],
            'stats' => $data['stats'],
            'accountGroups' => ChartOfAccount::query()
                ->where('status', 'Active')
                ->where('account_level', 'Group')
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name', 'account_type_id']),
            'ledgerAccounts' => ChartOfAccount::query()
                ->where('status', 'Active')
                ->where('account_level', 'Ledger')
                ->where('posting_allowed', true)
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name', 'parent_id', 'account_type_id']),
            'parties' => Party::query()
                ->where('status', 'Active')
                ->orderBy('party_name')
                ->get(['id', 'party_code', 'party_name']),
            'voucherTypes' => VoucherHeader::query()
                ->whereNotNull('voucher_type')
                ->where('voucher_type', '!=', '')
                ->distinct()
                ->orderBy('voucher_type')
                ->pluck('voucher_type'),
            'transactionHeads' => TransactionHead::query()
                ->where('status', 'Active')
                ->orderBy('name')
                ->get(['id', 'head_code', 'name']),
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('transaction-list'),
        ]);
    }

    public function show(int|string $voucherId): Response
    {
        $transaction = $this->reports->findTransaction($voucherId);
        abort_if(! $transaction, 404);

        return response()->view('accounting_reports.transactions.show', [
            'transaction' => $transaction,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('transaction-list'),
        ]);
    }

    public function reverse(Request $request, int|string $voucherId): RedirectResponse
    {
        $ability = config('accounting_reports.permissions.reverse_transactions');
        if ($ability) {
            $this->authorize($ability);
        }

        $result = $this->reversal->reverseVoucher($voucherId, $request->user()?->id);

        return redirect()
            ->route('accounting-reports.transactions.show', $voucherId)
            ->with('success', 'Transaction reversed successfully. Reversal voucher: ' . $result->voucher_no);
    }

    public function export(TransactionReportRequest $request, NativeReportExportService $exporter): SymfonyResponse
    {
        $filters = $request->filters();
        $filters['company_id'] = (int) ($request->user()?->company_id ?? 0);
        $rows = $this->reports->transactionBaseQuery($filters)
            ->orderByDesc('voucher_date')
            ->orderByDesc('voucher_id')
            ->get();

        $exportRows = $rows->map(fn ($row) => [
            $row->voucher_date,
            $row->voucher_no,
            $row->purpose_name,
            $row->party_name,
            $row->settlement,
            $row->nature,
            round((float) $row->amount, 2),
            $row->status,
            $row->reference_no,
        ])->all();

        return $exporter->download('Transaction List', ['Date', 'Voucher No', 'Transaction Head', 'Party', 'Settlement', 'Nature', 'Amount', 'Status', 'Reference'], $exportRows, [
            'Filtered Rows' => $rows->count(),
        ], $request->input('format', 'xlsx'), 'transaction-list-' . now()->format('Ymd-His'));
    }
}
