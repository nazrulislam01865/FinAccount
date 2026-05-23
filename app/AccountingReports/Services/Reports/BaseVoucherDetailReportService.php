<?php

namespace App\AccountingReports\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

abstract class BaseVoucherDetailReportService
{
    protected const REPORT_STATUSES = ['Posted', 'POSTED', 'posted', 'Reversed', 'REVERSED', 'reversed'];

    protected function basePostedLinesQuery(?int $companyId = null, ?string $fromDate = null, ?string $toDate = null): Builder
    {
        $query = DB::table('voucher_details as d')
            ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'd.account_id')
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->whereIn('v.status', self::REPORT_STATUSES)
            ->whereNull('v.deleted_at');

        if ($companyId) {
            $query->where('v.company_id', $companyId);
        }

        if ($fromDate) {
            $query->whereDate('v.voucher_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('v.voucher_date', '<=', $toDate);
        }

        return $query;
    }

    protected function ledgerBalanceRows(?int $companyId, ?string $fromDate, string $toDate, bool $includeInactive = false): Collection
    {
        $movement = $this->basePostedLinesQuery($companyId, $fromDate, $toDate)
            ->groupBy('d.account_id')
            ->selectRaw('d.account_id')
            ->selectRaw('COALESCE(SUM(d.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(d.credit), 0) AS credit');

        return DB::table('chart_of_accounts as a')
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin('chart_of_accounts as parent', 'parent.id', '=', 'a.parent_id')
            ->leftJoinSub($movement, 'm', 'm.account_id', '=', 'a.id')
            ->whereNull('a.deleted_at')
            ->where(function (Builder $query) {
                $query->where('a.posting_allowed', 1)
                    ->orWhereNotNull('m.account_id');
            })
            ->when($companyId, fn (Builder $query) => $query->where('a.company_id', $companyId))
            ->when(! $includeInactive, fn (Builder $query) => $query->where('a.status', 'Active'))
            ->orderBy('at.sort_order')
            ->orderBy('a.account_code')
            ->selectRaw('a.id AS account_id')
            ->selectRaw('a.account_code')
            ->selectRaw('a.account_name')
            ->selectRaw("COALESCE(parent.account_name, '') AS parent_account_name")
            ->selectRaw("COALESCE(at.name, a.ledger_type, 'Unclassified') AS account_type")
            ->selectRaw("COALESCE(a.ledger_type, '') AS ledger_type")
            ->selectRaw('COALESCE(a.is_cash_bank, 0) AS is_cash_bank')
            ->selectRaw('COALESCE(a.is_party_control, 0) AS is_party_control')
            ->selectRaw("COALESCE(a.status, 'Active') AS account_status")
            ->selectRaw('COALESCE(m.debit, 0) AS debit')
            ->selectRaw('COALESCE(m.credit, 0) AS credit')
            ->get()
            ->map(function (object $row) {
                $row->debit = round((float) $row->debit, 2);
                $row->credit = round((float) $row->credit, 2);
                $row->net_debit_balance = round($row->debit - $row->credit, 2);
                $row->net_credit_balance = round($row->credit - $row->debit, 2);
                $row->report_balance = $this->normalBalanceAmount($row);

                return $row;
            });
    }

    protected function normalBalanceAmount(object $row): float
    {
        return match ($row->account_type) {
            'Liability', 'Equity', 'Income' => round((float) $row->credit - (float) $row->debit, 2),
            default => round((float) $row->debit - (float) $row->credit, 2),
        };
    }

    protected function financialYearStartFor(string $date): string
    {
        $financialYear = DB::table('financial_years')
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('is_current')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->first(['start_date']);

        return $financialYear?->start_date ?: Carbon::parse($date)->startOfYear()->toDateString();
    }

    protected function accountTypes(): Collection
    {
        return DB::table('account_types')
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->pluck('name');
    }
}
