<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\PartyBalanceReportRequest;
use App\Services\Reports\NativeReportExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SupplierPayableController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(PartyBalanceReportRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reports->supplierPayables($filters);

        return response()->view('accounting_reports.customer_receivables.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('supplier-payables'),
        ]);
    }

    public function export(PartyBalanceReportRequest $request, NativeReportExportService $exporter): SymfonyResponse
    {
        $report = $this->reports->supplierPayables($request->filters());
        return $exporter->download($report['title'] ?? 'Supplier Payable', $this->headers(), $this->rows($report), ['Period' => ($report['from_date'] ?? '') . ' to ' . ($report['to_date'] ?? '')], $request->input('format', 'xlsx'), 'supplier-payable-' . now()->format('Ymd-His'));
    }

    private function headers(): array
    {
        return ['Party Code', 'Party Name', 'Ledger', 'Opening', 'Debit Movement', 'Credit Movement', 'Closing', 'Aging 0-30', 'Aging 31-60', 'Aging 61-90', 'Aging 90+'];
    }

    private function rows(array $report): array
    {
        return collect($report['rows'])->map(fn ($row) => [$row->party_code, $row->party_name, trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name), round((float) $row->opening_balance, 2), round((float) $row->debit_movement, 2), round((float) $row->credit_movement, 2), round((float) $row->closing_balance, 2), round((float) ($row->aging_0_30 ?? 0), 2), round((float) ($row->aging_31_60 ?? 0), 2), round((float) ($row->aging_61_90 ?? 0), 2), round((float) ($row->aging_90_plus ?? 0), 2)])->all();
    }
}
