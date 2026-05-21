<?php

namespace App\AccountingReports\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    private array $postedStatuses = ['Posted', 'POSTED', 'posted'];
    private array $draftStatuses = ['Draft', 'DRAFT', 'draft', 'Pending Review', 'PENDING_REVIEW'];
    private array $reversedStatuses = ['Reversed', 'REVERSED', 'reversed'];
    private array $cancelledStatuses = ['Cancelled', 'CANCELLED', 'cancelled'];

    public function paginateTransactions(array $filters = []): array
    {
        $base = $this->transactionBaseQuery($filters);

        $stats = DB::query()
            ->fromSub(clone $base, 't')
            ->selectRaw('COUNT(*) AS total_transactions')
            ->selectRaw("SUM(CASE WHEN nature = 'Receipt' THEN amount ELSE 0 END) AS total_receipt")
            ->selectRaw("SUM(CASE WHEN nature = 'Payment' THEN amount ELSE 0 END) AS total_payment")
            ->selectRaw("SUM(CASE WHEN status IN ('Draft','DRAFT','draft','Pending Review','PENDING_REVIEW') THEN 1 ELSE 0 END) AS total_draft")
            ->first();

        $transactions = DB::query()
            ->fromSub($base, 't')
            ->orderByDesc('voucher_date')
            ->orderByDesc('voucher_id')
            ->paginate((int) config('accounting_reports.per_page', 25))
            ->withQueryString();

        return [
            'transactions' => $transactions,
            'stats' => (object) [
                'total_transactions' => (int) ($stats->total_transactions ?? 0),
                'total_receipt' => (float) ($stats->total_receipt ?? 0),
                'total_payment' => (float) ($stats->total_payment ?? 0),
                'total_draft' => (int) ($stats->total_draft ?? 0),
            ],
        ];
    }

    public function transactionBaseQuery(array $filters = []): Builder
    {
        $settlementSub = DB::table('voucher_details as sd')
            ->join('chart_of_accounts as sa', 'sa.id', '=', 'sd.account_id')
            ->leftJoin('cash_bank_accounts as scb', 'scb.linked_ledger_account_id', '=', 'sa.id')
            ->where('sa.is_cash_bank', 1)
            ->groupBy('sd.voucher_header_id')
            ->selectRaw('sd.voucher_header_id')
            ->selectRaw("GROUP_CONCAT(DISTINCT COALESCE(scb.cash_bank_name, sa.account_name) ORDER BY COALESCE(scb.cash_bank_name, sa.account_name) SEPARATOR ', ') AS settlement_accounts");

        $query = DB::table('voucher_headers as v')
            ->leftJoin('transaction_heads as th', 'th.id', '=', 'v.transaction_head_id')
            ->leftJoin('settlement_types as st', 'st.id', '=', 'v.settlement_type_id')
            ->leftJoin('parties as p', 'p.id', '=', 'v.party_id')
            ->leftJoinSub($settlementSub, 'settlement', 'settlement.voucher_header_id', '=', 'v.id')
            ->whereNull('v.deleted_at')
            ->selectRaw('v.id AS voucher_id')
            ->selectRaw('v.voucher_number AS voucher_no')
            ->selectRaw('v.voucher_date AS voucher_date')
            ->selectRaw('v.voucher_type AS voucher_type_code')
            ->selectRaw('COALESCE(v.amount, 0) AS amount')
            ->selectRaw('v.status AS status')
            ->selectRaw('v.reference AS reference_no')
            ->selectRaw('v.notes AS description')
            ->selectRaw('NULL AS party_type')
            ->selectRaw('th.name AS purpose_name')
            ->selectRaw('th.head_code AS purpose_code')
            ->selectRaw('th.nature AS purpose_module')
            ->selectRaw('p.party_name AS party_name')
            ->selectRaw('COALESCE(settlement.settlement_accounts, st.name, v.cash_bank_effect, "") AS settlement')
            ->selectRaw($this->natureSql() . ' AS nature');

        if (! empty($filters['q'])) {
            $q = '%' . mb_strtolower(trim((string) $filters['q'])) . '%';
            $query->where(function (Builder $where) use ($q) {
                $where->whereRaw('LOWER(v.voucher_number) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(v.reference, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(v.notes, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(th.name, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(th.head_code, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(p.party_name, "")) LIKE ?', [$q]);
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('v.voucher_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('v.voucher_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'All') {
            $query->where('v.status', $filters['status']);
        }

        if (! empty($filters['nature']) && $filters['nature'] !== 'All') {
            $query->whereRaw($this->natureSql() . ' = ?', [$filters['nature']]);
        }

        return $query;
    }

    public function findTransaction(int|string $voucherId): ?object
    {
        $row = DB::query()
            ->fromSub($this->transactionBaseQuery(), 't')
            ->where('voucher_id', $voucherId)
            ->first();

        if (! $row) {
            return null;
        }

        $row->journal_lines = $this->journalLinesForVoucher($voucherId);
        return $row;
    }

    public function journalLinesForVoucher(int|string $voucherId): Collection
    {
        return DB::table('voucher_details as d')
            ->join('chart_of_accounts as a', 'a.id', '=', 'd.account_id')
            ->where('d.voucher_header_id', $voucherId)
            ->orderBy('d.line_no')
            ->orderBy('d.id')
            ->selectRaw('a.account_code AS account_code')
            ->selectRaw('a.account_name AS account_name')
            ->selectRaw('d.debit AS debit')
            ->selectRaw('d.credit AS credit')
            ->selectRaw('d.narration AS description')
            ->get();
    }

    public function cashBankBook(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $accountId = $filters['account_id'] ?? null;
        $bookType = $filters['book_type'] ?? 'All';
        $txnType = $filters['transaction_type'] ?? 'All';

        $opening = DB::query()
            ->fromSub(
                $this->cashBankMovementQuery($bookType, $accountId)
                    ->whereDate('v.voucher_date', '<', $fromDate),
                'm'
            )
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS opening_balance')
            ->value('opening_balance') ?? 0;

        $periodBase = $this->cashBankMovementQuery($bookType, $accountId)
            ->whereDate('v.voucher_date', '>=', $fromDate)
            ->whereDate('v.voucher_date', '<=', $toDate);

        if ($txnType === 'Inflow') {
            $periodBase->where('d.debit', '>', 0);
        } elseif ($txnType === 'Outflow') {
            $periodBase->where('d.credit', '>', 0);
        }

        $summary = DB::query()
            ->fromSub(clone $periodBase, 'm')
            ->selectRaw('COUNT(*) AS total_entries')
            ->selectRaw('COALESCE(SUM(debit),0) AS total_inflow')
            ->selectRaw('COALESCE(SUM(credit),0) AS total_outflow')
            ->first();

        $rows = DB::query()
            ->fromSub($periodBase, 'm')
            ->orderBy('journal_date')
            ->orderBy('journal_line_id')
            ->get();

        $running = (float) $opening;
        foreach ($rows as $row) {
            $running += (float) $row->debit - (float) $row->credit;
            $row->running_balance = $running;
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => (float) $opening,
            'total_inflow' => (float) ($summary->total_inflow ?? 0),
            'total_outflow' => (float) ($summary->total_outflow ?? 0),
            'closing_balance' => (float) $opening + (float) ($summary->total_inflow ?? 0) - (float) ($summary->total_outflow ?? 0),
            'total_entries' => (int) ($summary->total_entries ?? 0),
            'rows' => $rows,
            'account_balances' => $this->cashBankAccountBalances($bookType, $toDate),
            'cash_bank_accounts' => $this->cashBankAccounts($bookType),
        ];
    }

    public function cashBankMovementQuery(string $bookType = 'All', int|string|null $accountId = null): Builder
    {
        $query = DB::table('voucher_details as d')
            ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'd.account_id')
            ->leftJoin('cash_bank_accounts as cb', 'cb.linked_ledger_account_id', '=', 'a.id')
            ->where('a.is_cash_bank', 1)
            ->whereIn('v.status', array_merge($this->postedStatuses, $this->reversedStatuses))
            ->whereNull('v.deleted_at')
            ->selectRaw('d.id AS journal_line_id')
            ->selectRaw('v.voucher_date AS journal_date')
            ->selectRaw('v.voucher_type AS journal_no')
            ->selectRaw('v.voucher_number AS voucher_no')
            ->selectRaw('a.id AS account_id')
            ->selectRaw('a.account_code AS account_code')
            ->selectRaw('COALESCE(cb.cash_bank_name, a.account_name) AS account_name')
            ->selectRaw('COALESCE(cb.type, "") AS account_type')
            ->selectRaw('d.debit AS debit')
            ->selectRaw('d.credit AS credit')
            ->selectRaw('d.narration AS line_description')
            ->selectRaw('v.reference AS reference_no')
            ->selectRaw('v.notes AS voucher_description');

        if ($accountId) {
            $query->where('a.id', $accountId);
        }

        $this->applyBookTypeFilter($query, $bookType);

        return $query;
    }

    public function cashBankAccounts(string $bookType = 'All'): Collection
    {
        $query = DB::table('chart_of_accounts as a')
            ->leftJoin('cash_bank_accounts as cb', 'cb.linked_ledger_account_id', '=', 'a.id')
            ->where('a.is_cash_bank', 1)
            ->whereNull('a.deleted_at')
            ->selectRaw('a.id AS account_id')
            ->selectRaw('a.account_code AS account_code')
            ->selectRaw('COALESCE(cb.cash_bank_name, a.account_name) AS account_name')
            ->selectRaw('COALESCE(cb.type, "") AS account_type')
            ->orderBy('account_name');

        $this->applyBookTypeFilter($query, $bookType);

        return $query->get();
    }

    public function cashBankAccountBalances(string $bookType = 'All', ?string $toDate = null): Collection
    {
        $toDate ??= now()->toDateString();

        return DB::query()
            ->fromSub(
                $this->cashBankMovementQuery($bookType)->whereDate('v.voucher_date', '<=', $toDate),
                'm'
            )
            ->groupBy('account_id', 'account_code', 'account_name')
            ->selectRaw('account_id, account_code, account_name')
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS balance')
            ->orderBy('account_name')
            ->get();
    }

    private function applyBookTypeFilter(Builder $query, string $bookType): void
    {
        if ($bookType === 'All' || $bookType === 'Combined Book' || $bookType === '') {
            return;
        }

        if (str_contains(strtolower($bookType), 'cash')) {
            $query->where(function (Builder $where) {
                $where->whereRaw('LOWER(COALESCE(cb.type, "")) LIKE ?', ['%cash%'])
                    ->orWhereRaw('LOWER(COALESCE(cb.cash_bank_name, a.account_name)) LIKE ?', ['%cash%']);
            });
            return;
        }

        if (str_contains(strtolower($bookType), 'bank')) {
            $query->where(function (Builder $where) {
                $where->whereRaw('LOWER(COALESCE(cb.type, "")) LIKE ?', ['%bank%'])
                    ->orWhereRaw('LOWER(COALESCE(cb.type, "")) LIKE ?', ['%mobile%'])
                    ->orWhereRaw('LOWER(COALESCE(cb.cash_bank_name, a.account_name)) NOT LIKE ?', ['%cash%']);
            });
        }
    }

    private function natureSql(): string
    {
        return "CASE
            WHEN UPPER(COALESCE(th.nature, th.name, v.voucher_type, '')) LIKE '%ADVANCE%' THEN 'Advance'
            WHEN UPPER(COALESCE(v.voucher_type, th.nature, '')) LIKE '%RECEIPT%' OR UPPER(COALESCE(v.cash_bank_effect, '')) LIKE '%INFLOW%' THEN 'Receipt'
            WHEN UPPER(COALESCE(v.voucher_type, th.nature, '')) LIKE '%PAYMENT%' OR UPPER(COALESCE(v.cash_bank_effect, '')) LIKE '%OUTFLOW%' THEN 'Payment'
            WHEN UPPER(COALESCE(th.nature, th.name, v.party_ledger_effect, v.voucher_type, '')) LIKE '%DUE%' OR UPPER(COALESCE(v.party_ledger_effect, '')) LIKE '%RECEIVABLE%' OR UPPER(COALESCE(v.party_ledger_effect, '')) LIKE '%LIABILITY%' THEN 'Due'
            WHEN UPPER(COALESCE(v.voucher_type, '')) LIKE '%JOURNAL%' THEN 'Adjustment'
            ELSE 'Adjustment'
        END";
    }
}
