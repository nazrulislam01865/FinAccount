<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\CashBankBookRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(CashBankBookRequest $request): StreamedResponse
    {
        $report = $this->reports->cashBankBook(array_merge($request->filters(), ['company_id' => (int) ($request->user()?->company_id ?? 0)]));
        $fileName = 'cash-bank-book-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Opening Balance', number_format((float) $report['opening_balance'], 2, '.', '')]);
            fputcsv($out, ['Total Inflow', number_format((float) $report['total_inflow'], 2, '.', '')]);
            fputcsv($out, ['Total Outflow', number_format((float) $report['total_outflow'], 2, '.', '')]);
            fputcsv($out, ['Closing Balance', number_format((float) $report['closing_balance'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['Date', 'Voucher', 'Account', 'Particulars', 'Reference', 'Inflow', 'Outflow', 'Running Balance']);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row->journal_date,
                    $row->voucher_no ?: $row->journal_no,
                    trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name),
                    $row->line_description ?: $row->voucher_description,
                    $row->reference_no,
                    number_format((float) $row->debit, 2, '.', ''),
                    number_format((float) $row->credit, 2, '.', ''),
                    number_format((float) $row->running_balance, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
