<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\AccountMovementReportRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(AccountMovementReportRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reports->salesReport($filters);

        return response()->view('accounting_reports.sales_report.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('sales-report'),
        ]);
    }

    public function export(AccountMovementReportRequest $request): StreamedResponse
    {
        $report = $this->reports->salesReport($request->filters());
        return $this->download($report, 'sales-report');
    }

    private function download(array $report, string $prefix): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$report['title'], $report['from_date'] . ' to ' . $report['to_date']]);
            fputcsv($out, ['Date', 'Voucher', 'Head', 'Party', 'Ledger', 'Reference', 'Debit', 'Credit', 'Amount']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, [$row->voucher_date, $row->voucher_number, $row->transaction_head, $row->party_name, trim($row->account_code . ' - ' . $row->account_name), $row->reference, number_format((float) $row->debit, 2, '.', ''), number_format((float) $row->credit, 2, '.', ''), number_format((float) $row->amount, 2, '.', '')]);
            }
            fclose($out);
        }, $prefix . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
