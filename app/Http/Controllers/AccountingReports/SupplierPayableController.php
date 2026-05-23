<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\PartyBalanceReportRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPayableController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(PartyBalanceReportRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reports->supplierPayables($filters);

        return response()->view('accounting_reports.supplier_payables.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('supplier-payables'),
        ]);
    }

    public function export(PartyBalanceReportRequest $request): StreamedResponse
    {
        $report = $this->reports->supplierPayables($request->filters());

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$report['title'], $report['from_date'] . ' to ' . $report['to_date']]);
            fputcsv($out, ['Party Code', 'Party Name', 'Ledger', 'Opening', 'Debit Movement', 'Credit Movement', 'Closing']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, [$row->party_code, $row->party_name, trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name), number_format((float) $row->opening_balance, 2, '.', ''), number_format((float) $row->debit_movement, 2, '.', ''), number_format((float) $row->credit_movement, 2, '.', ''), number_format((float) $row->closing_balance, 2, '.', '')]);
            }
            fclose($out);
        }, 'supplier-payable-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
