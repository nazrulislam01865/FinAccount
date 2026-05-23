<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\CashFlowStatementRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowStatementController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(CashFlowStatementRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reports->cashFlowStatement($filters);

        return response()->view('accounting_reports.cash_flow_statement.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('cash-flow-statement'),
        ]);
    }

    public function export(CashFlowStatementRequest $request): StreamedResponse
    {
        $report = $this->reports->cashFlowStatement($request->filters());

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Cash Flow Statement', $report['from_date'] . ' to ' . $report['to_date']]);
            fputcsv($out, ['Opening Cash', number_format((float) $report['opening_cash'], 2, '.', '')]);
            fputcsv($out, ['Net Cash Flow', number_format((float) $report['net_cash_flow'], 2, '.', '')]);
            fputcsv($out, ['Closing Cash', number_format((float) $report['closing_cash'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Date', 'Voucher', 'Cash/Bank Account', 'Reference', 'Inflow', 'Outflow', 'Net']);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [$row->section, $row->voucher_date, $row->voucher_number, trim($row->cash_account_code . ' - ' . $row->cash_account_name), $row->reference, number_format((float) $row->cash_inflow, 2, '.', ''), number_format((float) $row->cash_outflow, 2, '.', ''), number_format((float) $row->net_cash_flow, 2, '.', '')]);
            }
            fclose($out);
        }, 'cash-flow-statement-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
