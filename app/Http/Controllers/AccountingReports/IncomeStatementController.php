<?php

namespace App\Http\Controllers\AccountingReports;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingReports\IncomeStatementRequest;
use App\Models\Company;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeStatementController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function index(IncomeStatementRequest $request): Response
    {
        $filters = $request->filters();
        $filters['company_id'] = (int) ($request->user()?->company_id ?? 0);
        $report = $this->reports->incomeStatement($filters);
        $company = $this->companyFor((int) ($request->user()?->company_id ?? 0));

        $view = ($filters['statement_format'] ?? 'management') === 'audit'
            ? 'accounting_reports.income_statement.audit'
            : 'accounting_reports.income_statement.index';

        return response()->view($view, [
            'filters' => $filters,
            'report' => $report,
            'company' => $company,
            'currency' => config('accounting_reports.currency', 'BDT'),
            'configuration' => $this->reports->reportConfiguration('income-statement'),
        ]);
    }

    public function export(IncomeStatementRequest $request): StreamedResponse
    {
        $filters = array_merge($request->filters(), ['company_id' => (int) ($request->user()?->company_id ?? 0)]);
        $report = $this->reports->incomeStatement($filters);
        $company = $this->companyFor((int) ($request->user()?->company_id ?? 0));
        $fileName = 'income-statement-' . ($filters['statement_format'] ?? 'management') . '-' . now()->format('Ymd-His') . '.csv';

        if (($filters['statement_format'] ?? 'management') === 'audit') {
            return $this->auditExport($report, $company, $fileName);
        }

        return $this->managementExport($report, $fileName);
    }

    private function managementExport(array $report, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['From Date', $report['from_date']]);
            fputcsv($out, ['To Date', $report['to_date']]);
            fputcsv($out, ['YTD Start', $report['year_start']]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Particulars', 'Account Code', 'Account Type', 'Amount', 'YTD Amount']);

            foreach (['Revenue', 'Cost of Services', 'Administrative & Selling Expenses', 'Financial Expenses', 'Other Income / Loss', 'Income Tax Expense'] as $section) {
                foreach ($report['groups']->get($section, collect()) as $row) {
                    fputcsv($out, [
                        $section,
                        $row->account_name,
                        $row->account_code,
                        $row->account_type,
                        number_format((float) $row->amount, 2, '.', ''),
                        number_format((float) $row->ytd_amount, 2, '.', ''),
                    ]);
                }
            }

            fputcsv($out, []);
            fputcsv($out, ['Total Revenue', '', '', '', number_format((float) $report['revenue'], 2, '.', ''), number_format((float) $report['ytd_revenue'], 2, '.', '')]);
            fputcsv($out, ['Total Cost of Services', '', '', '', number_format((float) $report['cost'], 2, '.', ''), number_format((float) $report['ytd_cost'], 2, '.', '')]);
            fputcsv($out, ['Gross Profit', '', '', '', number_format((float) $report['gross_profit'], 2, '.', ''), number_format((float) $report['ytd_gross_profit'], 2, '.', '')]);
            fputcsv($out, ['Administrative & Selling Expenses', '', '', '', number_format((float) $report['admin_selling_expense'], 2, '.', ''), number_format((float) $report['ytd_admin_selling_expense'], 2, '.', '')]);
            fputcsv($out, ['Operating Profit', '', '', '', number_format((float) $report['operating_profit'], 2, '.', ''), number_format((float) $report['ytd_operating_profit'], 2, '.', '')]);
            fputcsv($out, ['Financial Expenses', '', '', '', number_format((float) $report['financial_expense'], 2, '.', ''), number_format((float) $report['ytd_financial_expense'], 2, '.', '')]);
            fputcsv($out, ['Other Income', '', '', '', number_format((float) $report['other_income'], 2, '.', ''), number_format((float) $report['ytd_other_income'], 2, '.', '')]);
            fputcsv($out, ['Net Profit before Tax', '', '', '', number_format((float) $report['net_profit_before_tax'], 2, '.', ''), number_format((float) $report['ytd_net_profit_before_tax'], 2, '.', '')]);
            fputcsv($out, ['Income Tax Expenses', '', '', '', number_format((float) $report['income_tax_expense'], 2, '.', ''), number_format((float) $report['ytd_income_tax_expense'], 2, '.', '')]);
            fputcsv($out, ['Net Profit / Loss after Tax', '', '', '', number_format((float) $report['net_profit'], 2, '.', ''), number_format((float) $report['ytd_net_profit'], 2, '.', '')]);

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function auditExport(array $report, ?Company $company, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $company) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$company?->company_name ?? config('app.name')]);
            fputcsv($out, ['Statement of Profit or Loss and Other Comprehensive Income']);
            fputcsv($out, ['For the year ended ' . $this->auditDate($report['to_date'])]);
            fputcsv($out, []);
            fputcsv($out, ['Particulars', 'Notes', 'Amount in Taka ' . $report['current_period_label'], $report['previous_period_label']]);

            foreach ($report['audit_statement'] as $row) {
                fputcsv($out, [
                    $row['label'],
                    $row['note'],
                    is_null($row['current']) ? '' : number_format((float) $row['current'], 2, '.', ''),
                    is_null($row['previous']) ? '' : number_format((float) $row['previous'], 2, '.', ''),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['The annexed notes form an integral part of these Financial Statements.']);
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function companyFor(int $companyId): ?Company
    {
        return $companyId > 0 ? Company::find($companyId) : null;
    }

    private function auditDate(string $date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->format('jS F, Y');
    }
}
