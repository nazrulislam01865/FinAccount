<?php

namespace App\AccountingReports\Services\Reports;

use App\Services\Accounting\FinancialYearService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IncomeStatementReportService
{
    /**
     * Include posted accounting lines plus reversal lines so reversed vouchers net to zero.
     * Approved is included for installations where approval sets final reportability before
     * changing lifecycle_state/status to Posted.
     */
    private const REPORT_STATUSES = [
        'Posted', 'POSTED', 'posted',
        'Approved', 'APPROVED', 'approved',
        'Reversed', 'REVERSED', 'reversed',
    ];

    public function build(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $companyId = ! empty($filters['company_id']) ? (int) $filters['company_id'] : null;
        $includeZero = filter_var($filters['include_zero_balances'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $includeInactive = filter_var($filters['include_inactive_accounts'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $section = $this->normalizeSection((string) ($filters['section'] ?? 'All'));
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $yearStart = $this->financialYearStartFor($toDate, $companyId);

        [$previousFromDate, $previousToDate] = $this->previousComparablePeriod($fromDate, $toDate);

        $periodSub = $this->profitLossAggregateQuery($fromDate, $toDate, $companyId);
        $ytdSub = $this->profitLossAggregateQuery($yearStart, $toDate, $companyId);
        $previousSub = $this->profitLossAggregateQuery($previousFromDate, $previousToDate, $companyId);

        $rows = DB::table('chart_of_accounts as a')
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin('chart_of_accounts as parent', 'parent.id', '=', 'a.parent_id')
            ->leftJoinSub($periodSub, 'period', 'period.account_id', '=', 'a.id')
            ->leftJoinSub($ytdSub, 'ytd', 'ytd.account_id', '=', 'a.id')
            ->leftJoinSub($previousSub, 'previous_period', 'previous_period.account_id', '=', 'a.id')
            ->whereNull('a.deleted_at')
            ->when($companyId, fn (Builder $query) => $query->where('a.company_id', $companyId))
            ->when(! $includeInactive, fn (Builder $query) => $query->where('a.status', 'Active'))
            ->where(function (Builder $query) {
                $query->where('a.posting_allowed', 1)
                    ->orWhereNotNull('period.account_id')
                    ->orWhereNotNull('ytd.account_id')
                    ->orWhereNotNull('previous_period.account_id');
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $needle = '%' . $search . '%';
                $query->where(function (Builder $where) use ($needle) {
                    $where->whereRaw('LOWER(a.account_code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(a.account_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(parent.account_name, "")) LIKE ?', [$needle]);
                });
            })
            ->orderBy('at.sort_order')
            ->orderBy('a.account_code')
            ->selectRaw('a.id AS account_id')
            ->selectRaw('a.account_code')
            ->selectRaw('a.account_name')
            ->selectRaw("COALESCE(parent.account_name, '') AS parent_account_name")
            ->selectRaw("COALESCE(at.name, '') AS account_type_name")
            ->selectRaw("COALESCE(at.code, '') AS account_type_code")
            ->selectRaw("COALESCE(a.account_nature, '') AS account_nature")
            ->selectRaw("COALESCE(a.ledger_type, '') AS ledger_type")
            ->selectRaw("COALESCE(a.account_group, '') AS account_group")
            ->selectRaw("COALESCE(a.account_sub_group, '') AS account_sub_group")
            ->selectRaw('COALESCE(period.debit, 0) AS period_debit')
            ->selectRaw('COALESCE(period.credit, 0) AS period_credit')
            ->selectRaw('COALESCE(ytd.debit, 0) AS ytd_debit')
            ->selectRaw('COALESCE(ytd.credit, 0) AS ytd_credit')
            ->selectRaw('COALESCE(previous_period.debit, 0) AS previous_debit')
            ->selectRaw('COALESCE(previous_period.credit, 0) AS previous_credit')
            ->get()
            ->map(fn (object $row) => $this->decorateProfitLossRow($row))
            ->filter(fn (object $row) => $this->isProfitLossAccount($row))
            ->filter(fn (object $row) => $includeZero
                || abs((float) $row->amount) >= 0.01
                || abs((float) $row->ytd_amount) >= 0.01
                || abs((float) $row->previous_amount) >= 0.01)
            ->values();

        $displayRows = $section === 'All'
            ? $rows
            : $rows->filter(fn (object $row) => $row->section === $section)->values();

        $totals = $this->totals($rows);

        return array_merge([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'year_start' => $yearStart,
            'previous_from_date' => $previousFromDate,
            'previous_to_date' => $previousToDate,
            'current_period_label' => $this->periodYearLabel($fromDate, $toDate),
            'previous_period_label' => $this->periodYearLabel($previousFromDate, $previousToDate),
            'rows' => $displayRows,
            'groups' => $displayRows->groupBy('section'),
            'audit_statement' => $this->auditStatementRows($rows),
            'audit_notes' => config('accounting_reports.income_statement_notes', []),
        ], $totals);
    }

    private function profitLossAggregateQuery(string $fromDate, string $toDate, ?int $companyId = null): Builder
    {
        return DB::table('voucher_details as d')
            ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
            ->whereIn('v.status', self::REPORT_STATUSES)
            ->whereNull('v.deleted_at')
            ->when($companyId, fn (Builder $query) => $query->where('v.company_id', $companyId))
            ->whereDate('v.voucher_date', '>=', $fromDate)
            ->whereDate('v.voucher_date', '<=', $toDate)
            ->groupBy('d.account_id')
            ->selectRaw('d.account_id')
            ->selectRaw('COALESCE(SUM(d.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(d.credit), 0) AS credit');
    }

    private function decorateProfitLossRow(object $row): object
    {
        $row->account_type = $this->normalizedAccountType($row);
        $row->section = $this->profitLossSection($row);

        if ($row->account_type === 'Income') {
            $row->amount = round((float) $row->period_credit - (float) $row->period_debit, 2);
            $row->ytd_amount = round((float) $row->ytd_credit - (float) $row->ytd_debit, 2);
            $row->previous_amount = round((float) $row->previous_credit - (float) $row->previous_debit, 2);
        } else {
            $row->amount = round((float) $row->period_debit - (float) $row->period_credit, 2);
            $row->ytd_amount = round((float) $row->ytd_debit - (float) $row->ytd_credit, 2);
            $row->previous_amount = round((float) $row->previous_debit - (float) $row->previous_credit, 2);
        }

        return $row;
    }

    private function totals($rows): array
    {
        $revenue = round((float) $rows->where('section', 'Revenue')->sum('amount'), 2);
        $cost = round((float) $rows->where('section', 'Cost of Services')->sum('amount'), 2);
        $adminSellingExpense = round((float) $rows->where('section', 'Administrative & Selling Expenses')->sum('amount'), 2);
        $financialExpense = round((float) $rows->where('section', 'Financial Expenses')->sum('amount'), 2);
        $otherIncome = round((float) $rows->where('section', 'Other Income / Loss')->sum('amount'), 2);
        $incomeTaxExpense = round((float) $rows->where('section', 'Income Tax Expense')->sum('amount'), 2);

        $grossProfit = round($revenue - $cost, 2);
        $operatingProfit = round($grossProfit - $adminSellingExpense, 2);
        $netProfitBeforeTax = round($operatingProfit - $financialExpense + $otherIncome, 2);
        $netProfit = round($netProfitBeforeTax - $incomeTaxExpense, 2);
        $expense = round($adminSellingExpense + $financialExpense + $incomeTaxExpense, 2);

        $ytdRevenue = round((float) $rows->where('section', 'Revenue')->sum('ytd_amount'), 2);
        $ytdCost = round((float) $rows->where('section', 'Cost of Services')->sum('ytd_amount'), 2);
        $ytdAdminSellingExpense = round((float) $rows->where('section', 'Administrative & Selling Expenses')->sum('ytd_amount'), 2);
        $ytdFinancialExpense = round((float) $rows->where('section', 'Financial Expenses')->sum('ytd_amount'), 2);
        $ytdOtherIncome = round((float) $rows->where('section', 'Other Income / Loss')->sum('ytd_amount'), 2);
        $ytdIncomeTaxExpense = round((float) $rows->where('section', 'Income Tax Expense')->sum('ytd_amount'), 2);

        $ytdGrossProfit = round($ytdRevenue - $ytdCost, 2);
        $ytdOperatingProfit = round($ytdGrossProfit - $ytdAdminSellingExpense, 2);
        $ytdNetProfitBeforeTax = round($ytdOperatingProfit - $ytdFinancialExpense + $ytdOtherIncome, 2);
        $ytdNetProfit = round($ytdNetProfitBeforeTax - $ytdIncomeTaxExpense, 2);
        $ytdExpense = round($ytdAdminSellingExpense + $ytdFinancialExpense + $ytdIncomeTaxExpense, 2);

        $previousRevenue = round((float) $rows->where('section', 'Revenue')->sum('previous_amount'), 2);
        $previousCost = round((float) $rows->where('section', 'Cost of Services')->sum('previous_amount'), 2);
        $previousAdminSellingExpense = round((float) $rows->where('section', 'Administrative & Selling Expenses')->sum('previous_amount'), 2);
        $previousFinancialExpense = round((float) $rows->where('section', 'Financial Expenses')->sum('previous_amount'), 2);
        $previousOtherIncome = round((float) $rows->where('section', 'Other Income / Loss')->sum('previous_amount'), 2);
        $previousIncomeTaxExpense = round((float) $rows->where('section', 'Income Tax Expense')->sum('previous_amount'), 2);

        $previousGrossProfit = round($previousRevenue - $previousCost, 2);
        $previousOperatingProfit = round($previousGrossProfit - $previousAdminSellingExpense, 2);
        $previousNetProfitBeforeTax = round($previousOperatingProfit - $previousFinancialExpense + $previousOtherIncome, 2);
        $previousNetProfit = round($previousNetProfitBeforeTax - $previousIncomeTaxExpense, 2);

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'expense' => $expense,
            'admin_selling_expense' => $adminSellingExpense,
            'financial_expense' => $financialExpense,
            'other_income' => $otherIncome,
            'income_tax_expense' => $incomeTaxExpense,
            'gross_profit' => $grossProfit,
            'operating_profit' => $operatingProfit,
            'net_profit_before_tax' => $netProfitBeforeTax,
            'net_profit' => $netProfit,
            'ytd_revenue' => $ytdRevenue,
            'ytd_cost' => $ytdCost,
            'ytd_expense' => $ytdExpense,
            'ytd_admin_selling_expense' => $ytdAdminSellingExpense,
            'ytd_financial_expense' => $ytdFinancialExpense,
            'ytd_other_income' => $ytdOtherIncome,
            'ytd_income_tax_expense' => $ytdIncomeTaxExpense,
            'ytd_gross_profit' => $ytdGrossProfit,
            'ytd_operating_profit' => $ytdOperatingProfit,
            'ytd_net_profit_before_tax' => $ytdNetProfitBeforeTax,
            'ytd_net_profit' => $ytdNetProfit,
            'previous_revenue' => $previousRevenue,
            'previous_cost' => $previousCost,
            'previous_admin_selling_expense' => $previousAdminSellingExpense,
            'previous_financial_expense' => $previousFinancialExpense,
            'previous_other_income' => $previousOtherIncome,
            'previous_income_tax_expense' => $previousIncomeTaxExpense,
            'previous_gross_profit' => $previousGrossProfit,
            'previous_operating_profit' => $previousOperatingProfit,
            'previous_net_profit_before_tax' => $previousNetProfitBeforeTax,
            'previous_net_profit' => $previousNetProfit,
            'gross_margin' => $revenue != 0.0 ? round(($grossProfit / $revenue) * 100, 2) : 0.0,
            'net_margin' => $revenue != 0.0 ? round(($netProfit / $revenue) * 100, 2) : 0.0,
            'expense_ratio' => $revenue != 0.0 ? round(($expense / $revenue) * 100, 2) : 0.0,
        ];
    }

    private function auditStatementRows($rows): array
    {
        $notes = config('accounting_reports.income_statement_notes', []);
        $totals = $this->totals($rows);

        $line = function (string $key, string $label, ?string $noteKey = null, bool $bold = false, bool $deduct = false) use ($notes, $rows): array {
            return [
                'key' => $key,
                'label' => $label,
                'note' => $noteKey ? ($notes[$noteKey] ?? '') : '',
                'current' => round((float) $rows->where('section', $key)->sum('amount'), 2),
                'previous' => round((float) $rows->where('section', $key)->sum('previous_amount'), 2),
                'bold' => $bold,
                'deduct' => $deduct,
                'section_heading' => false,
            ];
        };

        return [
            $line('Revenue', config('accounting_reports.audit_income_statement_labels.revenue', 'Commission'), 'revenue'),
            $line('Cost of Services', 'Cost of Services', null, false, true),
            [
                'key' => 'gross_profit',
                'label' => 'Gross Profit',
                'note' => '',
                'current' => $totals['gross_profit'],
                'previous' => $totals['previous_gross_profit'],
                'bold' => true,
                'deduct' => false,
                'section_heading' => false,
            ],
            [
                'key' => 'operating_expenses_heading',
                'label' => 'Operating Expenses:',
                'note' => '',
                'current' => null,
                'previous' => null,
                'bold' => true,
                'deduct' => false,
                'section_heading' => true,
            ],
            $line('Administrative & Selling Expenses', 'Less: Administrative & Selling Expenses', 'admin_selling_expenses', false, true),
            [
                'key' => 'operating_profit',
                'label' => 'Operating Profit',
                'note' => '',
                'current' => $totals['operating_profit'],
                'previous' => $totals['previous_operating_profit'],
                'bold' => true,
                'deduct' => false,
                'section_heading' => false,
            ],
            $line('Financial Expenses', 'Financial Expenses', 'financial_expenses', false, true),
            $line('Other Income / Loss', 'Other Income', 'other_income'),
            [
                'key' => 'net_profit_before_tax',
                'label' => 'Net Profit before Tax',
                'note' => '',
                'current' => $totals['net_profit_before_tax'],
                'previous' => $totals['previous_net_profit_before_tax'],
                'bold' => true,
                'deduct' => false,
                'section_heading' => false,
            ],
            $line('Income Tax Expense', 'Less: Income Tax Expenses', 'income_tax_expense', false, true),
            [
                'key' => 'net_profit_after_tax',
                'label' => 'Net Profit / (Loss) after Tax',
                'note' => '',
                'current' => $totals['net_profit'],
                'previous' => $totals['previous_net_profit'],
                'bold' => true,
                'deduct' => false,
                'section_heading' => false,
            ],
        ];
    }

    private function financialYearStartFor(string $date, ?int $companyId = null): string
    {
        $financialYear = app(FinancialYearService::class)->currentForCompany($companyId);

        if ($financialYear) {
            return $financialYear->start_date->toDateString();
        }

        $financialYear = DB::table('financial_years')
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
            ->orderByDesc('is_current')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->first(['start_date']);

        return $financialYear?->start_date ?: Carbon::parse($date)->startOfYear()->toDateString();
    }

    private function previousComparablePeriod(string $fromDate, string $toDate): array
    {
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);

        return [
            $from->copy()->subYearNoOverflow()->toDateString(),
            $to->copy()->subYearNoOverflow()->toDateString(),
        ];
    }

    private function periodYearLabel(string $fromDate, string $toDate): string
    {
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);

        return $from->year === $to->year
            ? (string) $to->year
            : $from->format('Y') . '-' . $to->format('Y');
    }

    private function normalizedAccountType(object $row): string
    {
        $text = $this->rowText($row);

        if ($this->containsAny($text, ['income', 'revenue', 'sales', 'commission', 'service income', 'other income', 'gain'])) {
            return 'Income';
        }

        if ($this->containsAny($text, ['expense', 'cost', 'purchase', 'salary', 'rent', 'utility', 'fuel', 'bank charge', 'interest', 'tax'])) {
            return 'Expense';
        }

        return (string) ($row->account_type_name ?: '');
    }

    private function isProfitLossAccount(object $row): bool
    {
        return in_array($row->account_type, ['Income', 'Expense'], true);
    }

    private function profitLossSection(object $row): string
    {
        if ($row->account_type === 'Income') {
            return $this->matchesConfiguredKeyword($row, 'other_income_keywords')
                ? 'Other Income / Loss'
                : 'Revenue';
        }

        if ($this->matchesConfiguredKeyword($row, 'tax_expense_keywords')) {
            return 'Income Tax Expense';
        }

        if ($this->matchesConfiguredKeyword($row, 'financial_expense_keywords')) {
            return 'Financial Expenses';
        }

        if ($this->matchesConfiguredKeyword($row, 'cost_of_services_keywords')) {
            return 'Cost of Services';
        }

        return 'Administrative & Selling Expenses';
    }

    private function matchesConfiguredKeyword(object $row, string $configKey): bool
    {
        $text = $this->rowText($row);
        $keywords = config('accounting_reports.income_statement_sections.' . $configKey, []);

        foreach ($keywords as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function rowText(object $row): string
    {
        return mb_strtolower(implode(' ', array_filter([
            $row->account_name ?? '',
            $row->parent_account_name ?? '',
            $row->account_type_name ?? '',
            $row->account_type_code ?? '',
            $row->account_nature ?? '',
            $row->ledger_type ?? '',
            $row->account_group ?? '',
            $row->account_sub_group ?? '',
        ])));
    }

    private function normalizeSection(string $section): string
    {
        return match ($section) {
            'Cost of Sales' => 'Cost of Services',
            'Operating Expenses' => 'Administrative & Selling Expenses',
            default => $section,
        };
    }
}
