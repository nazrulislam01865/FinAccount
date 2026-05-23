<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\IncomeStatementRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeStatementController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(IncomeStatementRequest $request): Response
    {
        $filters = $request->filters();
        $filters['company_id'] = (int) ($request->user()?->company_id ?? 0);
        $report = $this->reports->incomeStatement($filters);

        return response()->view('accounting_reports.income_statement.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('income-statement'),
        ]);
    }

    public function export(IncomeStatementRequest $request): StreamedResponse
    {
        $report = $this->reports->incomeStatement(array_merge($request->filters(), ['company_id' => (int) ($request->user()?->company_id ?? 0)]));
        $fileName = 'income-statement-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['From Date', $report['from_date']]);
            fputcsv($out, ['To Date', $report['to_date']]);
            fputcsv($out, ['YTD Start', $report['year_start']]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Particulars', 'Account Code', 'Account Type', 'Amount', 'YTD Amount']);

            foreach (['Revenue', 'Cost of Sales', 'Operating Expenses'] as $section) {
                foreach ($report['groups']->get($section, collect()) as $row) {
                    fputcsv($out, [
                        $section,
                        $row->account_name,
                        $row->account_code,
                        $row->account_type,
                        number_format((float) $row->amount, 2, '.', ''),
                        number_format((float) $row->ytd_amount, 2, '.', ''),
                    ]);
                }
            }

            fputcsv($out, []);
            fputcsv($out, ['Total Revenue', '', '', '', number_format((float) $report['revenue'], 2, '.', ''), number_format((float) $report['ytd_revenue'], 2, '.', '')]);
            fputcsv($out, ['Total Cost of Sales', '', '', '', number_format((float) $report['cost'], 2, '.', ''), number_format((float) $report['ytd_cost'], 2, '.', '')]);
            fputcsv($out, ['Gross Profit', '', '', '', number_format((float) $report['gross_profit'], 2, '.', ''), number_format((float) $report['ytd_gross_profit'], 2, '.', '')]);
            fputcsv($out, ['Total Operating Expenses', '', '', '', number_format((float) $report['expense'], 2, '.', ''), number_format((float) $report['ytd_expense'], 2, '.', '')]);
            fputcsv($out, ['Net Profit / Loss', '', '', '', number_format((float) $report['net_profit'], 2, '.', ''), number_format((float) $report['ytd_net_profit'], 2, '.', '')]);

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
