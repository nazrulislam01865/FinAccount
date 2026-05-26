<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\AccountMovementReportRequest;
use App\Services\Reports\NativeReportExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExpenseReportController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(AccountMovementReportRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reports->expenseReport($filters);

        return response()->view('accounting_reports.sales_report.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('expense-report'),
        ]);
    }

    public function export(AccountMovementReportRequest $request, NativeReportExportService $exporter): SymfonyResponse
    {
        $report = $this->reports->expenseReport($request->filters());
        return $exporter->download($report['title'] ?? 'Expense Report', $this->headers(), $this->rows($report), ['Period' => ($report['from_date'] ?? '') . ' to ' . ($report['to_date'] ?? '')], $request->input('format', 'xlsx'), 'expense-report-' . now()->format('Ymd-His'));
    }

    private function headers(): array
    {
        return ['Date', 'Voucher', 'Head', 'Party', 'Ledger', 'Reference', 'Debit', 'Credit', 'Amount'];
    }

    private function rows(array $report): array
    {
        return collect($report['rows'])->map(fn ($row) => [$row->voucher_date, $row->voucher_number, $row->transaction_head, $row->party_name, trim($row->account_code . ' - ' . $row->account_name), $row->reference, round((float) $row->debit, 2), round((float) $row->credit, 2), round((float) $row->amount, 2)])->all();
    }
}
