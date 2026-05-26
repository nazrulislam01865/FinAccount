<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\BalanceSheetRequest;
use App\Services\Reports\NativeReportExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function export(BalanceSheetRequest $request, NativeReportExportService $exporter): SymfonyResponse
    {
        $report = $this->reports->balanceSheet($request->filters());
        $headers = ['Section', 'Code', 'Ledger', 'Parent', 'Balance'];
        $rows = collect($report['rows'])->map(fn ($row) => [
            $row->section,
            $row->account_code,
            $row->account_name,
            $row->parent_account_name,
            round((float) $row->report_balance, 2),
        ])->push(['TOTAL ASSETS', '', '', '', round((float) $report['assets'], 2)])
          ->push(['TOTAL LIABILITIES', '', '', '', round((float) $report['liabilities'], 2)])
          ->push(['TOTAL EQUITY', '', '', '', round((float) $report['equity'], 2)])
          ->push(['RETAINED PROFIT/LOSS', '', '', '', round((float) $report['retained_profit'], 2)])
          ->push(['DIFFERENCE', '', '', '', round((float) $report['difference'], 2)])
          ->all();

        return $exporter->download('Balance Sheet', $headers, $rows, [
            'As of Date' => $report['as_of_date'],
        ], $request->input('format', 'xlsx'), 'balance-sheet-' . now()->format('Ymd-His'));
    }
}
