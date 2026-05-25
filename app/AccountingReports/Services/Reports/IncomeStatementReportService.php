<?php

namespace App\AccountingReports\Services\Reports;

use App\Services\Accounting\FinancialYearService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class IncomeStatementReportService
{
    /**
     * Statuses included in financial reports. Reversed vouchers remain included so
     * opposite reversal lines naturally net the original voucher to zero.
     */
    private const REPORT_STATUSES = ['Posted', 'POSTED', 'posted', 'Reversed', 'REVERSED', 'reversed'];

    public function build(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $companyId = ! empty($filters['company_id']) ? (int) $filters['company_id'] : null;
        $includeZero = filter_var($filters['include_zero_balances'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $includeInactive = filter_var($filters['include_inactive_accounts'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $section = $filters['section'] ?? 'All';
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $yearStart = $this->financialYearStartFor($toDate, $companyId);

        $periodSub = $this->profitLossAggregateQuery($fromDate, $toDate, $companyId);
        $ytdSub = $this->profitLossAggregateQuery($yearStart, $toDate, $companyId);

        $rows = DB::table('chart_of_accounts as a')
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin('chart_of_accounts as parent', 'parent.id', '=', 'a.parent_id')
            ->leftJoinSub($periodSub, 'period', 'period.account_id', '=', 'a.id')
            ->leftJoinSub($ytdSub, 'ytd', 'ytd.account_id', '=', 'a.id')
            ->whereNull('a.deleted_at')
            ->when($companyId, fn (Builder $query) => $query->where('a.company_id', $companyId))
            ->when(! $includeInactive, fn (Builder $query) => $query->where('a.status', 'Active'))
            ->where('a.posting_allowed', 1)
            ->whereIn('at.name', ['Income', 'Expense'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $needle = '%' . $search . '%';
                $query->where(function (Builder $where) use ($needle) {
                    $where->whereRaw('LOWER(a.account_code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(a.account_name) LIKE ?', [$needle]);
                });
            })
            ->orderBy('at.sort_order')
            ->orderBy('a.account_code')
            ->selectRaw('a.id AS account_id')
            ->selectRaw('a.account_code')
            ->selectRaw('a.account_name')
            ->selectRaw("COALESCE(parent.account_name, '') AS parent_account_name")
            ->selectRaw('at.name AS account_type')
            ->selectRaw('COALESCE(period.debit, 0) AS period_debit')
            ->selectRaw('COALESCE(period.credit, 0) AS period_credit')
            ->selectRaw('COALESCE(ytd.debit, 0) AS ytd_debit')
            ->selectRaw('COALESCE(ytd.credit, 0) AS ytd_credit')
            ->get()
            ->map(fn (object $row) => $this->decorateProfitLossRow($row))
            ->filter(fn (object $row) => $includeZero || abs((float) $row->amount) >= 0.01 || abs((float) $row->ytd_amount) >= 0.01)
            ->values();

        $displayRows = $section === 'All'
            ? $rows
            : $rows->filter(fn (object $row) => $row->section === $section)->values();

        $totals = $this->totals($rows);

        return array_merge([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'year_start' => $yearStart,
            'rows' => $displayRows,
            'groups' => $displayRows->groupBy('section'),
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
        $row->section = $this->profitLossSection($row);

        if ($row->account_type === 'Income') {
            $row->amount = round((float) $row->period_credit - (float) $row->period_debit, 2);
            $row->ytd_amount = round((float) $row->ytd_credit - (float) $row->ytd_debit, 2);
        } else {
            $row->amount = round((float) $row->period_debit - (float) $row->period_credit, 2);
            $row->ytd_amount = round((float) $row->ytd_debit - (float) $row->ytd_credit, 2);
        }

        return $row;
    }

    private function totals($rows): array
    {
        $revenue = round((float) $rows->where('section', 'Revenue')->sum('amount'), 2);
        $cost = round((float) $rows->where('section', 'Cost of Sales')->sum('amount'), 2);
        $expense = round((float) $rows->where('section', 'Operating Expenses')->sum('amount'), 2);
        $grossProfit = round($revenue - $cost, 2);
        $netProfit = round($grossProfit - $expense, 2);

        $ytdRevenue = round((float) $rows->where('section', 'Revenue')->sum('ytd_amount'), 2);
        $ytdCost = round((float) $rows->where('section', 'Cost of Sales')->sum('ytd_amount'), 2);
        $ytdExpense = round((float) $rows->where('section', 'Operating Expenses')->sum('ytd_amount'), 2);
        $ytdGrossProfit = round($ytdRevenue - $ytdCost, 2);
        $ytdNetProfit = round($ytdGrossProfit - $ytdExpense, 2);

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'expense' => $expense,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'ytd_revenue' => $ytdRevenue,
            'ytd_cost' => $ytdCost,
            'ytd_expense' => $ytdExpense,
            'ytd_gross_profit' => $ytdGrossProfit,
            'ytd_net_profit' => $ytdNetProfit,
            'gross_margin' => $revenue != 0.0 ? round(($grossProfit / $revenue) * 100, 2) : 0.0,
            'net_margin' => $revenue != 0.0 ? round(($netProfit / $revenue) * 100, 2) : 0.0,
            'expense_ratio' => $revenue != 0.0 ? round(($expense / $revenue) * 100, 2) : 0.0,
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
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->first(['start_date']);

        return $financialYear?->start_date ?: Carbon::parse($date)->startOfYear()->toDateString();
    }

    private function profitLossSection(object $row): string
    {
        if ($row->account_type === 'Income') {
            return 'Revenue';
        }

        $name = mb_strtolower($row->account_name . ' ' . ($row->parent_account_name ?? ''));

        foreach (['cost of sales', 'direct cost', 'purchase', 'cogs', 'seed cost', 'stock cost'] as $needle) {
            if (str_contains($name, $needle)) {
                return 'Cost of Sales';
            }
        }

        return 'Operating Expenses';
    }
}
