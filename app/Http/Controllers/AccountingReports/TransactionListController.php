<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\AccountingReports\Services\AccountingReversalService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\TransactionReportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $data = $this->reports->paginateTransactions($filters);

        return response()->view('accounting_reports.transactions.index', [
            'filters' => $filters,
            'transactions' => $data['transactions'],
            'stats' => $data['stats'],
            'currency' => config('accounting_reports.currency', 'BDT'),
        ]);
    }

    public function show(int|string $voucherId): Response
    {
        $transaction = $this->reports->findTransaction($voucherId);
        abort_if(! $transaction, 404);

        return response()->view('accounting_reports.transactions.show', [
            'transaction' => $transaction,
            'currency' => config('accounting_reports.currency', 'BDT'),
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

    public function export(TransactionReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $rows = $this->reports->transactionBaseQuery($filters)
            ->orderByDesc('voucher_date')
            ->orderByDesc('voucher_id')
            ->get();

        $fileName = 'transaction-list-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Voucher No', 'Transaction Head', 'Party', 'Settlement', 'Nature', 'Amount', 'Status', 'Reference']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->voucher_date,
                    $row->voucher_no,
                    $row->purpose_name,
                    $row->party_name,
                    $row->settlement,
                    $row->nature,
                    number_format((float) $row->amount, 2, '.', ''),
                    $row->status,
                    $row->reference_no,
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
