<?php

namespace App\AccountingReports\Services\Reports;

use App\Services\Accounting\FinancialYearService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class BaseVoucherDetailReportService
{
    protected const REPORT_STATUSES = ['Posted', 'POSTED', 'posted', 'Reversed', 'REVERSED', 'reversed'];

    /**
     * Base report line query for Phase 3.
     *
     * Journal lines are now the SRS source of truth. The query intentionally
     * exposes legacy aliases (d.account_id, d.debit, d.credit, v.voucher_date,
     * etc.) so older report services and Blade views keep working while their
     * source moves from voucher_details to journal_lines. If a deployment has
     * not run the Phase 2 journal migration yet, the method safely falls back
     * to voucher_details.
     */
    protected function basePostedLinesQuery(?int $companyId = null, ?string $fromDate = null, ?string $toDate = null): Builder
    {
        $query = $this->reportLineQuery($companyId)
            ->join('chart_of_accounts as a', 'a.id', '=', 'd.account_id')
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id');

        if ($fromDate) {
            $query->whereDate('v.voucher_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('v.voucher_date', '<=', $toDate);
        }

        return $query;
    }

    protected function reportLineQuery(?int $companyId = null): Builder
    {
        $query = $this->usesJournalLines()
            ? $this->journalLineCompatibilityQuery()
            : DB::table('voucher_details as d')
                ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
                ->whereNull('v.deleted_at');

        $query->whereIn('v.status', self::REPORT_STATUSES);

        if ($companyId) {
            $query->where('v.company_id', $companyId);
        }

        return $query;
    }

    protected function usesJournalLines(): bool
    {
        try {
            return Schema::hasTable('journal_headers') && Schema::hasTable('journal_lines');
        } catch (\Throwable) {
            return false;
        }
    }

    private function journalLineCompatibilityQuery(): Builder
    {
        $detailSub = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->selectRaw('jl.id AS id')
            ->selectRaw('COALESCE(jh.voucher_header_id, 0) AS voucher_header_id')
            ->selectRaw('jl.line_no AS line_no')
            ->selectRaw('jl.ledger_id AS account_id')
            ->selectRaw('jl.party_id AS party_id')
            ->selectRaw('jl.branch_id AS branch_id')
            ->selectRaw('jl.rule_line_id AS rule_line_id')
            ->selectRaw('jl.amount_source AS amount_source')
            ->selectRaw('jl.entry_type AS entry_type')
            ->selectRaw('jl.debit_amount AS debit')
            ->selectRaw('jl.credit_amount AS credit')
            ->selectRaw('jl.line_narration AS narration')
            ->selectRaw('jl.created_at AS created_at')
            ->selectRaw('jl.updated_at AS updated_at');

        $headerSub = DB::table('journal_headers as jh')
            ->leftJoin('voucher_headers as vh', 'vh.id', '=', 'jh.voucher_header_id')
            ->selectRaw('COALESCE(jh.voucher_header_id, 0) AS id')
            ->selectRaw('jh.id AS journal_header_id')
            ->selectRaw('jh.company_id AS company_id')
            ->selectRaw('jh.financial_year_id AS financial_year_id')
            ->selectRaw('jh.voucher_number AS voucher_number')
            ->selectRaw('jh.voucher_type AS voucher_type')
            ->selectRaw('jh.journal_date AS voucher_date')
            ->selectRaw('jh.transaction_head_id AS transaction_head_id')
            ->selectRaw('COALESCE(vh.settlement_type_id, NULL) AS settlement_type_id')
            ->selectRaw('jh.party_id AS party_id')
            ->selectRaw('jh.amount AS amount')
            ->selectRaw('jh.total_debit AS total_debit')
            ->selectRaw('jh.total_credit AS total_credit')
            ->selectRaw('COALESCE(vh.party_ledger_effect, NULL) AS party_ledger_effect')
            ->selectRaw('COALESCE(vh.cash_bank_effect, NULL) AS cash_bank_effect')
            ->selectRaw('COALESCE(vh.reference, NULL) AS reference')
            ->selectRaw('COALESCE(vh.notes, jh.narration) AS notes')
            ->selectRaw('jh.status AS status')
            ->selectRaw('vh.deleted_at AS deleted_at')
            ->selectRaw('jh.created_at AS created_at')
            ->selectRaw('jh.updated_at AS updated_at');

        return DB::query()
            ->fromSub($detailSub, 'd')
            ->joinSub($headerSub, 'v', 'v.id', '=', 'd.voucher_header_id')
            ->whereNull('v.deleted_at');
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
            ->where(function (Builder $query) {
                $query->where(function (Builder $activeAccount) {
                    $activeAccount->whereNull('a.deleted_at')
                        ->where('a.posting_allowed', 1);
                })
                    ->orWhereNotNull('m.account_id');
            })
            ->when($companyId, fn (Builder $query) => $query->where('a.company_id', $companyId))
            ->when(! $includeInactive, fn (Builder $query) => $query->where(function (Builder $statusQuery) {
                $statusQuery->where('a.status', 'Active')
                    ->orWhereNotNull('a.deleted_at');
            }))
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

    protected function financialYearStartFor(string $date, ?int $companyId = null): string
    {
        $financialYear = app(FinancialYearService::class)->currentForCompany($companyId);

        if ($financialYear) {
            return $financialYear->start_date->toDateString();
        }

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
