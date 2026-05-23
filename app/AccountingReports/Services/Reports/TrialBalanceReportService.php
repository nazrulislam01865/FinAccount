<?php

namespace App\AccountingReports\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceReportService
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
        $accountType = $filters['account_type'] ?? 'All';
        $balanceType = $filters['balance_type'] ?? 'All';
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        $openingSub = $this->accountMovementQuery($companyId)
            ->whereDate('v.voucher_date', '<', $fromDate)
            ->groupBy('d.account_id')
            ->selectRaw('d.account_id')
            ->selectRaw('COALESCE(SUM(d.debit), 0) AS opening_debit_raw')
            ->selectRaw('COALESCE(SUM(d.credit), 0) AS opening_credit_raw');

        $periodSub = $this->accountMovementQuery($companyId)
            ->whereDate('v.voucher_date', '>=', $fromDate)
            ->whereDate('v.voucher_date', '<=', $toDate)
            ->groupBy('d.account_id')
            ->selectRaw('d.account_id')
            ->selectRaw('COALESCE(SUM(d.debit), 0) AS period_debit')
            ->selectRaw('COALESCE(SUM(d.credit), 0) AS period_credit');

        $rows = DB::table('chart_of_accounts as a')
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoinSub($openingSub, 'op', 'op.account_id', '=', 'a.id')
            ->leftJoinSub($periodSub, 'pd', 'pd.account_id', '=', 'a.id')
            ->whereNull('a.deleted_at')
            ->when($companyId, fn (Builder $query) => $query->where('a.company_id', $companyId))
            ->when(! $includeInactive, fn (Builder $query) => $query->where('a.status', 'Active'))
            ->where(function (Builder $query) {
                $query->where('a.posting_allowed', 1)
                    ->orWhereNotNull('op.account_id')
                    ->orWhereNotNull('pd.account_id');
            })
            ->when($accountType !== 'All' && $accountType !== '', function (Builder $query) use ($accountType) {
                $query->where('at.name', $accountType);
            })
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
            ->selectRaw("COALESCE(at.name, 'Unclassified') AS account_type")
            ->selectRaw("COALESCE(a.normal_balance, at.normal_balance, 'Debit') AS normal_balance")
            ->selectRaw('COALESCE(op.opening_debit_raw, 0) AS opening_debit_raw')
            ->selectRaw('COALESCE(op.opening_credit_raw, 0) AS opening_credit_raw')
            ->selectRaw('COALESCE(pd.period_debit, 0) AS period_debit')
            ->selectRaw('COALESCE(pd.period_credit, 0) AS period_credit')
            ->get()
            ->map(fn (object $row) => $this->decorateTrialBalanceRow($row))
            ->filter(fn (object $row) => $includeZero || $row->has_activity)
            ->filter(fn (object $row) => $this->matchesBalanceType($row, $balanceType))
            ->values();

        $totalDebit = round((float) $rows->sum('closing_debit'), 2);
        $totalCredit = round((float) $rows->sum('closing_credit'), 2);
        $difference = round($totalDebit - $totalCredit, 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'rows' => $rows,
            'groups' => $rows->groupBy('account_type'),
            'account_types' => $this->accountTypes(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01,
            'max_debit' => $rows->sortByDesc('closing_debit')->first(),
            'max_credit' => $rows->sortByDesc('closing_credit')->first(),
            'zero_count' => $rows->filter(fn (object $row) => $row->closing_debit == 0.0 && $row->closing_credit == 0.0)->count(),
        ];
    }

    private function accountMovementQuery(?int $companyId = null): Builder
    {
        return DB::table('voucher_details as d')
            ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
            ->whereIn('v.status', self::REPORT_STATUSES)
            ->whereNull('v.deleted_at')
            ->when($companyId, fn (Builder $query) => $query->where('v.company_id', $companyId));
    }

    private function decorateTrialBalanceRow(object $row): object
    {
        $openingNet = (float) $row->opening_debit_raw - (float) $row->opening_credit_raw;
        $closingNet = $openingNet + (float) $row->period_debit - (float) $row->period_credit;

        $row->opening_debit = round(max($openingNet, 0), 2);
        $row->opening_credit = round(max($openingNet * -1, 0), 2);
        $row->period_debit = round((float) $row->period_debit, 2);
        $row->period_credit = round((float) $row->period_credit, 2);
        $row->closing_debit = round(max($closingNet, 0), 2);
        $row->closing_credit = round(max($closingNet * -1, 0), 2);
        $row->has_activity = $row->opening_debit > 0
            || $row->opening_credit > 0
            || $row->period_debit > 0
            || $row->period_credit > 0
            || $row->closing_debit > 0
            || $row->closing_credit > 0;

        return $row;
    }

    private function matchesBalanceType(object $row, string $balanceType): bool
    {
        return match ($balanceType) {
            'Debit' => $row->closing_debit > 0,
            'Credit' => $row->closing_credit > 0,
            'Zero' => $row->closing_debit == 0.0 && $row->closing_credit == 0.0,
            default => true,
        };
    }

    private function accountTypes(): Collection
    {
        return DB::table('account_types')
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->pluck('name');
    }
}
