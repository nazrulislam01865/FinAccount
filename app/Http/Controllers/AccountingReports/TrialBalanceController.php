<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\TrialBalanceRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalanceController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(TrialBalanceRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reports->trialBalance($filters);

        return response()->view('accounting_reports.trial_balance.index', [
            'filters' => $filters,
            'report' => $report,
            'currency' => config('accounting_reports.currency', 'BDT'),
        ]);
    }

    public function export(TrialBalanceRequest $request): StreamedResponse
    {
        $report = $this->reports->trialBalance($request->filters());
        $fileName = 'trial-balance-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['From Date', $report['from_date']]);
            fputcsv($out, ['To Date', $report['to_date']]);
            fputcsv($out, ['Total Closing Debit', number_format((float) $report['total_debit'], 2, '.', '')]);
            fputcsv($out, ['Total Closing Credit', number_format((float) $report['total_credit'], 2, '.', '')]);
            fputcsv($out, ['Difference', number_format(abs((float) $report['difference']), 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['Code', 'Ledger Account', 'Account Type', 'Opening Debit', 'Opening Credit', 'Period Debit', 'Period Credit', 'Closing Debit', 'Closing Credit']);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row->account_code,
                    $row->account_name,
                    $row->account_type,
                    number_format((float) $row->opening_debit, 2, '.', ''),
                    number_format((float) $row->opening_credit, 2, '.', ''),
                    number_format((float) $row->period_debit, 2, '.', ''),
                    number_format((float) $row->period_credit, 2, '.', ''),
                    number_format((float) $row->closing_debit, 2, '.', ''),
                    number_format((float) $row->closing_credit, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
