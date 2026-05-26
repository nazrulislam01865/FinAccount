<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\TrialBalanceRequest;
use App\Services\Reports\NativeReportExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TrialBalanceController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(TrialBalanceRequest $request): Response
    {
        $filters = $request->filters();
        $filters['company_id'] = (int) ($request->user()?->company_id ?? 0);
        $report = $this->reports->trialBalance($filters);

        return response()->view('accounting_reports.trial_balance.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('trial-balance'),
        ]);
    }

    public function export(TrialBalanceRequest $request, NativeReportExportService $exporter): SymfonyResponse
    {
        $report = $this->reports->trialBalance(array_merge($request->filters(), ['company_id' => (int) ($request->user()?->company_id ?? 0)]));
        $headers = ['Code', 'Ledger Account', 'Account Type', 'Opening Debit', 'Opening Credit', 'Period Debit', 'Period Credit', 'Closing Debit', 'Closing Credit'];
        $rows = collect($report['rows'])->map(fn ($row) => [
            $row->account_code,
            $row->account_name,
            $row->account_type,
            round((float) $row->opening_debit, 2),
            round((float) $row->opening_credit, 2),
            round((float) $row->period_debit, 2),
            round((float) $row->period_credit, 2),
            round((float) $row->closing_debit, 2),
            round((float) $row->closing_credit, 2),
        ])->push([
            'TOTAL', '', '', '', '', '', '',
            round((float) $report['total_debit'], 2),
            round((float) $report['total_credit'], 2),
        ])->all();

        return $exporter->download('Trial Balance', $headers, $rows, [
            'From Date' => $report['from_date'],
            'To Date' => $report['to_date'],
            'Difference' => round(abs((float) $report['difference']), 2),
        ], $request->input('format', 'xlsx'), 'trial-balance-' . now()->format('Ymd-His'));
    }
}
