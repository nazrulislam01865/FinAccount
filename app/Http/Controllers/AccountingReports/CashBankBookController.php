<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\CashBankBookRequest;
use App\Services\Reports\NativeReportExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CashBankBookController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(CashBankBookRequest $request): Response
    {
        $filters = $request->filters();
        $filters['company_id'] = (int) ($request->user()?->company_id ?? 0);
        $data = $this->reports->cashBankBook($filters);

        return response()->view('accounting_reports.cash_bank_book.index', [
            'filters' => $filters,
            'report' => $data,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('cash-bank-book'),
        ]);
    }

    public function export(CashBankBookRequest $request, NativeReportExportService $exporter): SymfonyResponse
    {
        $report = $this->reports->cashBankBook(array_merge($request->filters(), ['company_id' => (int) ($request->user()?->company_id ?? 0)]));
        $headers = ['Date', 'Voucher', 'Account', 'Particulars', 'Reference', 'Inflow', 'Outflow', 'Running Balance'];
        $rows = collect($report['rows'])->map(fn ($row) => [
            $row->journal_date,
            $row->voucher_no ?: $row->journal_no,
            trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name),
            $row->line_description ?: $row->voucher_description,
            $row->reference_no,
            round((float) $row->debit, 2),
            round((float) $row->credit, 2),
            round((float) $row->running_balance, 2),
        ])->all();

        return $exporter->download('Cash / Bank Book', $headers, $rows, [
            'Opening Balance' => round((float) $report['opening_balance'], 2),
            'Total Inflow' => round((float) $report['total_inflow'], 2),
            'Total Outflow' => round((float) $report['total_outflow'], 2),
            'Closing Balance' => round((float) $report['closing_balance'], 2),
        ], $request->input('format', 'xlsx'), 'cash-bank-book-' . now()->format('Ymd-His'));
    }
}
