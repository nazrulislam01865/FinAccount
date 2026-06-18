<?php

namespace App\Services\Accounting\Reports;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Party;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /** @return array<string, mixed> */
    public function balanceSheet(int $companyId, array $filters = []): array
    {
        $asOfDate = $this->date($filters['as_of_date'] ?? null, now()->toDateString());
        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['Asset', 'Liability', 'Equity'])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $balances = $this->accountBalancesAsOf($accounts, $companyId, $asOfDate);

        $rows = $accounts
            ->map(function (ChartOfAccount $account) use ($balances): array {
                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $account->normal_balance,
                    'is_active' => (bool) $account->is_active,
                    'balance' => round((float) ($balances[$account->id] ?? 0), 2),
                ];
            })
            ->filter(function (array $row) use ($includeZero, $search): bool {
                if (! $includeZero && abs((float) $row['balance']) < 0.01) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower($row['code'].' '.$row['name'].' '.$row['type']), $search);
            })
            ->values();

        $incomeSummary = $this->profitLossTotals(
            $companyId,
            null,
            $asOfDate,
        );

        $retainedProfit = round($incomeSummary['income'] - $incomeSummary['expense'], 2);
        $assets = round((float) $rows->where('type', 'Asset')->sum('balance'), 2);
        $liabilities = round((float) $rows->where('type', 'Liability')->sum('balance'), 2);
        $equity = round((float) $rows->where('type', 'Equity')->sum('balance'), 2);
        $liabilitiesAndEquity = round($liabilities + $equity + $retainedProfit, 2);
        $difference = round($assets - $liabilitiesAndEquity, 2);

        return [
            'as_of_date' => $asOfDate,
            'include_zero_balances' => $includeZero,
            'search' => $search,
            'rows' => $rows,
            'groups' => $rows->groupBy('type'),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'retained_profit' => $retainedProfit,
            'liabilities_and_equity' => $liabilitiesAndEquity,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01,
        ];
    }

    /** @return array<string, mixed> */
    public function incomeStatement(int $companyId, array $filters = []): array
    {
        $fromDate = $this->date($filters['from_date'] ?? null, now()->startOfMonth()->toDateString());
        $toDate = $this->date($filters['to_date'] ?? null, now()->toDateString());

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['Income', 'Expense'])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $balances = $this->accountMovementsForPeriod($accounts, $companyId, $fromDate, $toDate);

        $rows = $accounts
            ->map(function (ChartOfAccount $account) use ($balances): array {
                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $account->normal_balance,
                    'is_active' => (bool) $account->is_active,
                    'amount' => round((float) ($balances[$account->id] ?? 0), 2),
                ];
            })
            ->filter(function (array $row) use ($includeZero, $search): bool {
                if (! $includeZero && abs((float) $row['amount']) < 0.01) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower($row['code'].' '.$row['name'].' '.$row['type']), $search);
            })
            ->values();

        $income = round((float) $rows->where('type', 'Income')->sum('amount'), 2);
        $expense = round((float) $rows->where('type', 'Expense')->sum('amount'), 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero_balances' => $includeZero,
            'search' => $search,
            'rows' => $rows,
            'groups' => $rows->groupBy('type'),
            'income' => $income,
            'expense' => $expense,
            'net_profit' => round($income - $expense, 2),
        ];
    }

    /** @return array<string, mixed> */
    public function trialBalance(int $companyId, array $filters = []): array
    {
        $fromDate = $this->date($filters['from_date'] ?? null, now()->startOfMonth()->toDateString());
        $toDate = $this->date($filters['to_date'] ?? null, now()->toDateString());

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $accountType = (string) ($filters['account_type'] ?? 'all');
        $balanceType = in_array(($filters['balance_type'] ?? 'all'), ['debit', 'credit', 'zero'], true)
            ? (string) $filters['balance_type']
            : 'all';

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->when($accountType !== 'all' && $accountType !== '', fn ($query) => $query->where('type', $accountType))
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $opening = $this->openingNetMap($companyId, $fromDate);
        $movement = $this->journalMovementMap($companyId, $fromDate, $toDate);

        $rows = $accounts
            ->map(function (ChartOfAccount $account) use ($opening, $movement): array {
                $openingNet = round((float) ($opening[$account->id] ?? 0), 2);
                $period = $movement[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];
                $periodDebit = round((float) $period['debit'], 2);
                $periodCredit = round((float) $period['credit'], 2);
                $closingNet = round($openingNet + $periodDebit - $periodCredit, 2);

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $account->normal_balance,
                    'is_active' => (bool) $account->is_active,
                    'opening_debit' => round(max($openingNet, 0), 2),
                    'opening_credit' => round(max($openingNet * -1, 0), 2),
                    'period_debit' => $periodDebit,
                    'period_credit' => $periodCredit,
                    'closing_debit' => round(max($closingNet, 0), 2),
                    'closing_credit' => round(max($closingNet * -1, 0), 2),
                    'has_activity' => abs($openingNet) >= 0.01 || $periodDebit >= 0.01 || $periodCredit >= 0.01 || abs($closingNet) >= 0.01,
                ];
            })
            ->filter(function (array $row) use ($includeZero, $search, $balanceType): bool {
                if (! $includeZero && ! $row['has_activity']) {
                    return false;
                }

                if ($balanceType === 'debit' && (float) $row['closing_debit'] <= 0) {
                    return false;
                }

                if ($balanceType === 'credit' && (float) $row['closing_credit'] <= 0) {
                    return false;
                }

                if ($balanceType === 'zero' && ((float) $row['closing_debit'] > 0 || (float) $row['closing_credit'] > 0)) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower($row['code'].' '.$row['name'].' '.$row['type']), $search);
            })
            ->values();

        $totalOpeningDebit = round((float) $rows->sum('opening_debit'), 2);
        $totalOpeningCredit = round((float) $rows->sum('opening_credit'), 2);
        $totalPeriodDebit = round((float) $rows->sum('period_debit'), 2);
        $totalPeriodCredit = round((float) $rows->sum('period_credit'), 2);
        $totalClosingDebit = round((float) $rows->sum('closing_debit'), 2);
        $totalClosingCredit = round((float) $rows->sum('closing_credit'), 2);
        $difference = round($totalClosingDebit - $totalClosingCredit, 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'account_type' => $accountType,
            'balance_type' => $balanceType,
            'include_zero_balances' => $includeZero,
            'search' => $search,
            'account_types' => ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->values(),
            'rows' => $rows,
            'groups' => $rows->groupBy('type'),
            'total_opening_debit' => $totalOpeningDebit,
            'total_opening_credit' => $totalOpeningCredit,
            'total_period_debit' => $totalPeriodDebit,
            'total_period_credit' => $totalPeriodCredit,
            'total_closing_debit' => $totalClosingDebit,
            'total_closing_credit' => $totalClosingCredit,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01,
        ];
    }

    /** @return array<string, mixed> */
    public function ledgerReport(int $companyId, array $filters = []): array
    {
        $fromDate = $this->date($filters['from_date'] ?? null, now()->startOfMonth()->toDateString());
        $toDate = $this->date($filters['to_date'] ?? null, now()->toDateString());

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $accountId = filled($filters['account_id'] ?? null) ? (int) $filters['account_id'] : null;
        $partyId = filled($filters['party_id'] ?? null) ? (int) $filters['party_id'] : null;
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        $selectedAccount = $accountId
            ? $accounts->firstWhere('id', $accountId)
            : $accounts->firstWhere('is_active', true) ?? $accounts->first();

        $parties = Party::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        $selectedParty = $partyId ? $parties->firstWhere('id', $partyId) : null;

        if (! $selectedAccount) {
            return [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'account_id' => null,
                'party_id' => $partyId,
                'search' => $search,
                'accounts' => $accounts,
                'parties' => $parties,
                'account' => null,
                'party' => $selectedParty,
                'rows' => collect(),
                'opening_debit' => 0.0,
                'opening_credit' => 0.0,
                'period_debit' => 0.0,
                'period_credit' => 0.0,
                'closing_debit' => 0.0,
                'closing_credit' => 0.0,
            ];
        }

        $openingNet = $this->ledgerOpeningNet($companyId, (int) $selectedAccount->id, $fromDate, $selectedParty?->id);
        $runningNet = $openingNet;

        $query = JournalLine::query()
            ->with(['journalEntry.transaction.transactionHead', 'party', 'moneyAccount'])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_lines.chart_of_account_id', $selectedAccount->id)
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate])
            ->when($selectedParty, fn ($query) => $query->where('journal_lines.party_id', $selectedParty->id))
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.$search.'%';
                $query->where(function ($where) use ($needle): void {
                    $where->whereRaw('LOWER(journal_entries.voucher_no) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(journal_entries.narration, "")) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(journal_lines.description, "")) LIKE ?', [$needle]);
                });
            })
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.sequence')
            ->select('journal_lines.*', 'journal_entries.entry_date as ledger_entry_date', 'journal_entries.voucher_no as ledger_voucher_no', 'journal_entries.narration as ledger_narration');

        $lines = $query->get();

        $rows = $lines->map(function (JournalLine $line) use (&$runningNet): array {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $runningNet = round($runningNet + $debit - $credit, 2);
            $entry = $line->journalEntry;

            return [
                'date' => $entry?->entry_date?->toDateString() ?? (string) $line->ledger_entry_date,
                'voucher_no' => $entry?->voucher_no ?? (string) $line->ledger_voucher_no,
                'transaction_head' => $entry?->transaction?->transactionHead?->name,
                'party' => $line->party?->code ? $line->party->code.' — '.$line->party->name : null,
                'money_account' => $line->moneyAccount?->name,
                'description' => $line->description ?: ($entry?->narration ?? (string) $line->ledger_narration),
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round(abs($runningNet), 2),
                'balance_type' => $runningNet >= 0 ? 'Dr' : 'Cr',
            ];
        });

        $periodDebit = round((float) $lines->sum('debit'), 2);
        $periodCredit = round((float) $lines->sum('credit'), 2);
        $closingNet = round($openingNet + $periodDebit - $periodCredit, 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'account_id' => $selectedAccount->id,
            'party_id' => $selectedParty?->id,
            'search' => $search,
            'accounts' => $accounts,
            'parties' => $parties,
            'account' => $selectedAccount,
            'party' => $selectedParty,
            'rows' => $rows,
            'opening_debit' => round(max($openingNet, 0), 2),
            'opening_credit' => round(max($openingNet * -1, 0), 2),
            'period_debit' => $periodDebit,
            'period_credit' => $periodCredit,
            'closing_debit' => round(max($closingNet, 0), 2),
            'closing_credit' => round(max($closingNet * -1, 0), 2),
        ];
    }

    /** @return array<string, mixed> */
    public function dueReport(int $companyId, array $filters = []): array
    {
        $asOfDate = $this->date($filters['as_of_date'] ?? null, now()->toDateString());
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $dueType = in_array(($filters['due_type'] ?? 'all'), ['receivable', 'payable'], true)
            ? (string) $filters['due_type']
            : 'all';
        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);

        $parties = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $rows = collect();

        foreach ($parties as $party) {
            if ($search !== '' && ! str_contains(mb_strtolower($party->code.' '.$party->name.' '.$party->type), $search)) {
                continue;
            }

            if ($dueType !== 'payable' && $party->receivable_account_id && $party->receivableAccount) {
                $row = $this->partyDueRow($companyId, $party, $party->receivableAccount, 'Receivable', $asOfDate);
                if ($includeZero || abs((float) $row['closing_balance']) >= 0.01) {
                    $rows->push($row);
                }
            }

            if ($dueType !== 'receivable' && $party->payable_account_id && $party->payableAccount) {
                $row = $this->partyDueRow($companyId, $party, $party->payableAccount, 'Payable', $asOfDate);
                if ($includeZero || abs((float) $row['closing_balance']) >= 0.01) {
                    $rows->push($row);
                }
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'search' => $search,
            'due_type' => $dueType,
            'include_zero_balances' => $includeZero,
            'rows' => $rows->values(),
            'groups' => $rows->groupBy('due_type'),
            'total_receivable' => round((float) $rows->where('due_type', 'Receivable')->sum('closing_balance'), 2),
            'total_payable' => round((float) $rows->where('due_type', 'Payable')->sum('closing_balance'), 2),
            'aging_totals' => [
                'current' => round((float) $rows->sum('current'), 2),
                'days_31_60' => round((float) $rows->sum('days_31_60'), 2),
                'days_61_90' => round((float) $rows->sum('days_61_90'), 2),
                'days_90_plus' => round((float) $rows->sum('days_90_plus'), 2),
            ],
        ];
    }

    /** @param Collection<int, ChartOfAccount> $accounts @return array<int, float> */
    private function accountBalancesAsOf(Collection $accounts, int $companyId, string $asOfDate): array
    {
        $openingAndMovement = $this->openingNetMap($companyId, null);
        $movement = $this->journalMovementMap($companyId, null, $asOfDate);

        return $accounts->mapWithKeys(function (ChartOfAccount $account) use ($openingAndMovement, $movement): array {
            $journal = $movement[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];
            $rawNet = (float) ($openingAndMovement[$account->id] ?? 0)
                + (float) $journal['debit']
                - (float) $journal['credit'];

            return [$account->id => $this->normalBalanceAmount($account, $rawNet)];
        })->all();
    }

    /** @param Collection<int, ChartOfAccount> $accounts @return array<int, float> */
    private function accountMovementsForPeriod(Collection $accounts, int $companyId, string $fromDate, string $toDate): array
    {
        $journalMovement = $this->journalMovementMap($companyId, $fromDate, $toDate);

        return $accounts->mapWithKeys(function (ChartOfAccount $account) use ($journalMovement): array {
            $movement = $journalMovement[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];
            $debit = (float) $movement['debit'];
            $credit = (float) $movement['credit'];

            return [$account->id => $this->normalBalanceAmount($account, $debit - $credit)];
        })->all();
    }

    /** @return array<int, float> */
    private function openingNetMap(int $companyId, ?string $beforeDate): array
    {
        $journal = $beforeDate === null
            ? collect()
            : JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.company_id', $companyId)
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.status', 'posted')
                ->whereDate('journal_entries.entry_date', '<', $beforeDate)
                ->groupBy('journal_lines.chart_of_account_id')
                ->selectRaw('journal_lines.chart_of_account_id, COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) AS net')
                ->pluck('net', 'chart_of_account_id');

        $moneyOpening = MoneyAccount::query()
            ->selectRaw('chart_of_account_id, SUM(opening_balance) AS opening')
            ->where('company_id', $companyId)
            ->groupBy('chart_of_account_id')
            ->pluck('opening', 'chart_of_account_id');

        $receivableOpening = Party::query()
            ->selectRaw('receivable_account_id, SUM(opening_balance) AS opening')
            ->where('company_id', $companyId)
            ->whereNotNull('receivable_account_id')
            ->groupBy('receivable_account_id')
            ->pluck('opening', 'receivable_account_id');

        $payableOpening = Party::query()
            ->selectRaw('payable_account_id, SUM(opening_balance) AS opening')
            ->where('company_id', $companyId)
            ->whereNotNull('payable_account_id')
            ->groupBy('payable_account_id')
            ->pluck('opening', 'payable_account_id');

        $ids = collect()
            ->merge($journal->keys())
            ->merge($moneyOpening->keys())
            ->merge($receivableOpening->keys())
            ->merge($payableOpening->keys())
            ->unique();

        return $ids->mapWithKeys(fn ($accountId): array => [
            (int) $accountId => round(
                (float) ($journal[$accountId] ?? 0)
                + (float) ($moneyOpening[$accountId] ?? 0)
                + (float) ($receivableOpening[$accountId] ?? 0)
                - (float) ($payableOpening[$accountId] ?? 0),
                2,
            ),
        ])->all();
    }

    /** @return array<int, array{debit: float, credit: float}> */
    private function journalMovementMap(int $companyId, ?string $fromDate, string $toDate): array
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->when($fromDate !== null, fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $fromDate))
            ->whereDate('journal_entries.entry_date', '<=', $toDate)
            ->groupBy('journal_lines.chart_of_account_id')
            ->selectRaw('journal_lines.chart_of_account_id, COALESCE(SUM(journal_lines.debit), 0) AS debit_total, COALESCE(SUM(journal_lines.credit), 0) AS credit_total')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->chart_of_account_id => [
                    'debit' => round((float) $row->debit_total, 2),
                    'credit' => round((float) $row->credit_total, 2),
                ],
            ])
            ->all();
    }

    private function ledgerOpeningNet(int $companyId, int $accountId, string $fromDate, ?int $partyId = null): float
    {
        if ($partyId === null) {
            $map = $this->openingNetMap($companyId, $fromDate);

            return round((float) ($map[$accountId] ?? 0), 2);
        }

        $party = Party::query()
            ->where('company_id', $companyId)
            ->find($partyId);

        $opening = 0.0;
        if ($party && (int) $party->receivable_account_id === $accountId) {
            $opening += (float) $party->opening_balance;
        }
        if ($party && (int) $party->payable_account_id === $accountId) {
            $opening -= (float) $party->opening_balance;
        }

        $journalNet = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_lines.chart_of_account_id', $accountId)
            ->where('journal_lines.party_id', $partyId)
            ->whereDate('journal_entries.entry_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) AS net')
            ->value('net');

        return round($opening + (float) $journalNet, 2);
    }

    /** @return array{income: float, expense: float} */
    private function profitLossTotals(int $companyId, ?string $fromDate, string $toDate): array
    {
        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['Income', 'Expense'])
            ->get();

        $balances = $fromDate === null
            ? $this->accountBalancesAsOf($accounts, $companyId, $toDate)
            : $this->accountMovementsForPeriod($accounts, $companyId, $fromDate, $toDate);

        return [
            'income' => round((float) $accounts->where('type', 'Income')->sum(fn (ChartOfAccount $account): float => (float) ($balances[$account->id] ?? 0)), 2),
            'expense' => round((float) $accounts->where('type', 'Expense')->sum(fn (ChartOfAccount $account): float => (float) ($balances[$account->id] ?? 0)), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function partyDueRow(int $companyId, Party $party, ChartOfAccount $account, string $dueType, string $asOfDate): array
    {
        $lines = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.entry_date', '<=', $asOfDate)
            ->where('journal_lines.party_id', $party->id)
            ->where('journal_lines.chart_of_account_id', $account->id)
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->get([
                'journal_lines.id',
                'journal_lines.debit',
                'journal_lines.credit',
                'journal_entries.entry_date',
            ]);

        $opening = round((float) $party->opening_balance, 2);
        $periodDebit = round((float) $lines->sum('debit'), 2);
        $periodCredit = round((float) $lines->sum('credit'), 2);
        $movement = round($dueType === 'Receivable'
            ? $periodDebit - $periodCredit
            : $periodCredit - $periodDebit, 2);
        $closing = round($opening + $movement, 2);
        $aging = $this->agingBuckets($lines, $dueType, $opening, $asOfDate);

        return [
            'party_id' => $party->id,
            'party_code' => $party->code,
            'party_name' => $party->name,
            'party_type' => $party->type,
            'due_type' => $dueType,
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'opening_balance' => $opening,
            'period_debit' => $periodDebit,
            'period_credit' => $periodCredit,
            'closing_balance' => $closing,
            'current' => round($aging['current'], 2),
            'days_31_60' => round($aging['days_31_60'], 2),
            'days_61_90' => round($aging['days_61_90'], 2),
            'days_90_plus' => round($aging['days_90_plus'], 2),
        ];
    }

    /** @param Collection<int, JournalLine> $lines @return array{current: float, days_31_60: float, days_61_90: float, days_90_plus: float} */
    private function agingBuckets(Collection $lines, string $dueType, float $opening, string $asOfDate): array
    {
        $items = collect();

        if ($opening > 0) {
            $items->push(['date' => null, 'remaining' => $opening]);
        }

        foreach ($lines as $line) {
            $amount = $dueType === 'Receivable'
                ? (float) $line->debit - (float) $line->credit
                : (float) $line->credit - (float) $line->debit;

            if ($amount > 0) {
                $items->push(['date' => (string) $line->entry_date, 'remaining' => round($amount, 2)]);
                continue;
            }

            $payment = abs($amount);
            foreach ($items as $index => $item) {
                if ($payment <= 0) {
                    break;
                }

                $remaining = (float) $item['remaining'];
                $applied = min($remaining, $payment);
                $items[$index] = ['date' => $item['date'], 'remaining' => round($remaining - $applied, 2)];
                $payment = round($payment - $applied, 2);
            }
        }

        $asOf = CarbonImmutable::parse($asOfDate)->startOfDay();
        $buckets = ['current' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_90_plus' => 0.0];

        foreach ($items as $item) {
            $remaining = round((float) $item['remaining'], 2);
            if ($remaining <= 0) {
                continue;
            }

            if ($item['date'] === null) {
                $buckets['days_90_plus'] += $remaining;
                continue;
            }

            $age = CarbonImmutable::parse((string) $item['date'])->startOfDay()->diffInDays($asOf, false);

            if ($age <= 30) {
                $buckets['current'] += $remaining;
            } elseif ($age <= 60) {
                $buckets['days_31_60'] += $remaining;
            } elseif ($age <= 90) {
                $buckets['days_61_90'] += $remaining;
            } else {
                $buckets['days_90_plus'] += $remaining;
            }
        }

        return $buckets;
    }

    private function normalBalanceAmount(ChartOfAccount $account, float $debitLessCredit): float
    {
        return round($account->normal_balance === 'Credit' ? -$debitLessCredit : $debitLessCredit, 2);
    }

    private function date(?string $value, string $fallback): string
    {
        if (! $value) {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
