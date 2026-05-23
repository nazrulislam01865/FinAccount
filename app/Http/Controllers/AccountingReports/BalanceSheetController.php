<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\BalanceSheetRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(BalanceSheetRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reports->balanceSheet($filters);

        return response()->view('accounting_reports.balance_sheet.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('balance-sheet'),
        ]);
    }

    public function export(BalanceSheetRequest $request): StreamedResponse
    {
        $report = $this->reports->balanceSheet($request->filters());

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Balance Sheet as of', $report['as_of_date']]);
            fputcsv($out, ['Assets', number_format((float) $report['assets'], 2, '.', '')]);
            fputcsv($out, ['Liabilities', number_format((float) $report['liabilities'], 2, '.', '')]);
            fputcsv($out, ['Equity', number_format((float) $report['equity'], 2, '.', '')]);
            fputcsv($out, ['Retained Profit/Loss', number_format((float) $report['retained_profit'], 2, '.', '')]);
            fputcsv($out, ['Difference', number_format((float) $report['difference'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Code', 'Ledger', 'Parent', 'Balance']);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [$row->section, $row->account_code, $row->account_name, $row->parent_account_name, number_format((float) $row->report_balance, 2, '.', '')]);
            }
            fclose($out);
        }, 'balance-sheet-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
