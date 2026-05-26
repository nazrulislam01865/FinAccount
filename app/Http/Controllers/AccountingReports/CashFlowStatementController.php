<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\CashFlowStatementRequest;
use App\Services\Reports\NativeReportExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function export(CashFlowStatementRequest $request, NativeReportExportService $exporter): SymfonyResponse
    {
        $report = $this->reports->cashFlowStatement($request->filters());
        $headers = ['Section', 'Date', 'Voucher', 'Cash/Bank Account', 'Reference', 'Inflow', 'Outflow', 'Net'];
        $rows = collect($report['rows'])->map(fn ($row) => [$row->section, $row->voucher_date, $row->voucher_number, trim($row->cash_account_code . ' - ' . $row->cash_account_name), $row->reference, round((float) $row->cash_inflow, 2), round((float) $row->cash_outflow, 2), round((float) $row->net_cash_flow, 2)])->all();

        return $exporter->download('Cash Flow Statement', $headers, $rows, [
            'From Date' => $report['from_date'],
            'To Date' => $report['to_date'],
            'Opening Cash' => round((float) $report['opening_cash'], 2),
            'Net Cash Flow' => round((float) $report['net_cash_flow'], 2),
            'Closing Cash' => round((float) $report['closing_cash'], 2),
        ], $request->input('format', 'xlsx'), 'cash-flow-statement-' . now()->format('Ymd-His'));
    }
}
