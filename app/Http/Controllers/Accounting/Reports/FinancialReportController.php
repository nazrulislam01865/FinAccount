<?php

namespace App\Http\Controllers\Accounting\Reports;

use App\Http\Controllers\Controller;
use App\Services\Accounting\Reports\FinancialReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function __construct(private readonly FinancialReportService $service) {}

    public function balanceSheet(Request $request): View|StreamedResponse
    {
        $filters = $this->filters($request, ['as_of_date', 'search', 'include_zero_balances']);
        $report = $this->service->balanceSheet((int) $request->user()->company_id, $filters);

        if ($request->query('export') === 'csv') {
            return $this->balanceSheetCsv($report);
        }

        return view('reports.balance-sheet', compact('report'));
    }

    public function incomeStatement(Request $request): View|StreamedResponse
    {
        $filters = $this->filters($request, ['from_date', 'to_date', 'search', 'include_zero_balances']);
        $report = $this->service->incomeStatement((int) $request->user()->company_id, $filters);

        if ($request->query('export') === 'csv') {
            return $this->incomeStatementCsv($report);
        }

        return view('reports.income-statement', compact('report'));
    }

    public function trialBalance(Request $request): View|StreamedResponse
    {
        $filters = $this->filters($request, [
            'from_date', 'to_date', 'account_type', 'balance_type', 'search', 'include_zero_balances',
        ]);
        $report = $this->service->trialBalance((int) $request->user()->company_id, $filters);

        if ($request->query('export') === 'csv') {
            return $this->trialBalanceCsv($report);
        }

        return view('reports.trial-balance', compact('report'));
    }

    public function ledgerReport(Request $request): View|StreamedResponse
    {
        $filters = $this->filters($request, [
            'from_date', 'to_date', 'account_id', 'party_id', 'search',
        ]);
        $report = $this->service->ledgerReport((int) $request->user()->company_id, $filters);

        if ($request->query('export') === 'csv') {
            return $this->ledgerReportCsv($report);
        }

        return view('reports.ledger-report', compact('report'));
    }

    public function dueReport(Request $request): View|StreamedResponse
    {
        $filters = $this->filters($request, ['as_of_date', 'search', 'due_type', 'include_zero_balances']);
        $report = $this->service->dueReport((int) $request->user()->company_id, $filters);

        if ($request->query('export') === 'csv') {
            return $this->dueReportCsv($report);
        }

        return view('reports.due-report', compact('report'));
    }

    /** @param array<int, string> $keys @return array<string, mixed> */
    private function filters(Request $request, array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $request->query($key)])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }

    /** @param array<string, mixed> $report */
    private function balanceSheetCsv(array $report): StreamedResponse
    {
        return $this->csv('balance-sheet-'.$report['as_of_date'].'.csv', function ($handle) use ($report): void {
            fputcsv($handle, ['Balance Sheet As Of', $report['as_of_date']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Type', 'Section', 'Account Code', 'Account Name', 'Balance']);

            foreach (['Asset', 'Liability', 'Equity'] as $type) {
                foreach (($report['section_groups']->get($type, collect())) as $section => $rows) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [$type, $section, $row['code'], $row['name'], $row['balance']]);
                    }

                    fputcsv($handle, [$type, 'Total '.$section, '', '', $rows->sum('balance')]);
                }
            }

            fputcsv($handle, ['Equity', 'Retained Profit / Loss', '', '', $report['retained_profit']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Total Assets', '', '', '', $report['assets']]);
            fputcsv($handle, ['Total Liabilities', '', '', '', $report['liabilities']]);
            fputcsv($handle, ['Total Equity', '', '', '', $report['equity']]);
            fputcsv($handle, ['Liabilities + Equity', '', '', '', $report['liabilities_and_equity']]);
            fputcsv($handle, ['Difference', '', '', '', $report['difference']]);
        });
    }

    /** @param array<string, mixed> $report */
    private function incomeStatementCsv(array $report): StreamedResponse
    {
        return $this->csv('income-statement-'.$report['from_date'].'-to-'.$report['to_date'].'.csv', function ($handle) use ($report): void {
            fputcsv($handle, ['Income Statement', $report['from_date'].' to '.$report['to_date']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Section', 'Account Code', 'Account Name', 'Amount']);

            foreach ([
                'Revenue', 'Cost of Sales', 'Operating Expense', 'Administrative Expense', 'Selling Expense',
                'Other Income', 'Financial Expense', 'Tax Expense',
            ] as $section) {
                foreach (($report['sections']->get($section, collect())) as $row) {
                    fputcsv($handle, [$section, $row['code'], $row['name'], $row['amount']]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Revenue', '', '', $report['revenue']]);
            fputcsv($handle, ['Cost of Sales', '', '', $report['cost_of_sales']]);
            fputcsv($handle, ['Gross Profit', '', '', $report['gross_profit']]);
            fputcsv($handle, ['Operating Expenses', '', '', $report['operating_expenses']]);
            fputcsv($handle, ['Operating Profit', '', '', $report['operating_profit']]);
            fputcsv($handle, ['Other Income', '', '', $report['other_income']]);
            fputcsv($handle, ['Financial Expense', '', '', $report['financial_expense']]);
            fputcsv($handle, ['Net Profit Before Tax', '', '', $report['net_profit_before_tax']]);
            fputcsv($handle, ['Tax Expense', '', '', $report['tax_expense']]);
            fputcsv($handle, ['Net Profit / Loss', '', '', $report['net_profit']]);
        });
    }

    /** @param array<string, mixed> $report */
    private function trialBalanceCsv(array $report): StreamedResponse
    {
        return $this->csv('trial-balance-'.$report['from_date'].'-to-'.$report['to_date'].'.csv', function ($handle) use ($report): void {
            fputcsv($handle, ['Trial Balance', $report['from_date'].' to '.$report['to_date']]);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Type', 'Account Code', 'Account Name', 'Opening Debit', 'Opening Credit',
                'Period Debit', 'Period Credit', 'Closing Debit', 'Closing Credit',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['type'],
                    $row['code'],
                    $row['name'],
                    $row['opening_debit'],
                    $row['opening_credit'],
                    $row['period_debit'],
                    $row['period_credit'],
                    $row['closing_debit'],
                    $row['closing_credit'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Total', '', '',
                $report['total_opening_debit'],
                $report['total_opening_credit'],
                $report['total_period_debit'],
                $report['total_period_credit'],
                $report['total_closing_debit'],
                $report['total_closing_credit'],
            ]);
            fputcsv($handle, ['Closing Difference', '', '', '', '', '', '', $report['difference']]);
        });
    }

    /** @param array<string, mixed> $report */
    private function ledgerReportCsv(array $report): StreamedResponse
    {
        $accountCode = $report['account']?->code ?? 'account';

        return $this->csv('ledger-'.$accountCode.'-'.$report['from_date'].'-to-'.$report['to_date'].'.csv', function ($handle) use ($report): void {
            fputcsv($handle, ['Ledger Report', $report['from_date'].' to '.$report['to_date']]);
            fputcsv($handle, ['Account', $report['account'] ? $report['account']->code.' - '.$report['account']->name : 'Not selected']);
            if ($report['party']) {
                fputcsv($handle, ['Party', $report['party']->code.' - '.$report['party']->name]);
            }
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Voucher No', 'Transaction Head', 'Party', 'Description', 'Debit', 'Credit', 'Balance', 'Dr/Cr']);
            fputcsv($handle, ['Opening', '', '', '', 'Opening Balance', $report['opening_debit'], $report['opening_credit'], '', '']);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['voucher_no'],
                    $row['transaction_head'],
                    $row['party'],
                    $row['description'],
                    $row['debit'],
                    $row['credit'],
                    $row['balance'],
                    $row['balance_type'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Total', '', '', '', '', $report['period_debit'], $report['period_credit']]);
            fputcsv($handle, ['Closing', '', '', '', '', $report['closing_debit'], $report['closing_credit']]);
        });
    }

    /** @param array<string, mixed> $report */
    private function dueReportCsv(array $report): StreamedResponse
    {
        return $this->csv('due-report-'.$report['as_of_date'].'.csv', function ($handle) use ($report): void {
            fputcsv($handle, ['Due Report As Of', $report['as_of_date']]);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Type', 'Party Code', 'Party Name', 'Party Type', 'Account', 'Opening',
                'Debit Movement', 'Credit Movement', 'Closing Due', '0-30', '31-60', '61-90', '90+',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['due_type'],
                    $row['party_code'],
                    $row['party_name'],
                    $row['party_type'],
                    $row['account_code'].' - '.$row['account_name'],
                    $row['opening_balance'],
                    $row['period_debit'],
                    $row['period_credit'],
                    $row['closing_balance'],
                    $row['current'],
                    $row['days_31_60'],
                    $row['days_61_90'],
                    $row['days_90_plus'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Total Receivable', '', '', '', '', '', '', '', $report['total_receivable']]);
            fputcsv($handle, ['Total Payable', '', '', '', '', '', '', '', $report['total_payable']]);
        });
    }

    private function csv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer): void {
            $handle = fopen('php://output', 'w');
            $writer($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
