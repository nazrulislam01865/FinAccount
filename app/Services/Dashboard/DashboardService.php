<?php

namespace App\Services\Dashboard;

use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\ChartOfAccountBalanceService;
use App\Services\Accounting\PartyService;
use App\Support\CompanyContext;
use App\Support\TransactionTypes;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const PERIODS = ['month', 'today', 'week', 'quarter'];

    public function __construct(
        private readonly AccountingOptionService $optionService,
        private readonly ChartOfAccountBalanceService $accountBalanceService,
        private readonly PartyService $partyService,
    ) {}

    /** @return array<string, mixed> */
    public function summary(?int $companyId, string $requestedPeriod = 'month'): array
    {
        $period = in_array($requestedPeriod, self::PERIODS, true) ? $requestedPeriod : 'month';
        $window = $this->periodWindow($period);
        $categoryLabels = $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY);

        if (! $companyId) {
            return $this->emptySummary($period, $window, $categoryLabels);
        }

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();
        $accountBalances = $this->accountBalanceService->balancesFor($accounts, $companyId);

        $moneyAccounts = MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('chart_of_account_id')
            ->orderBy('id')
            ->get();

        $moneyAccountBalances = $this->accountBalanceService->balancesForMoneyAccounts($moneyAccounts, $companyId);
        $moneyAccountRows = $this->moneyAccountRows($moneyAccounts, $moneyAccountBalances);
        $availableMoney = (float) $moneyAccountRows->sum('balance');

        $parties = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $partyBalances = $this->partyService->balancesFor($parties, $companyId);
        [$receivables, $payables] = $this->partyRows($parties, $partyBalances);
        $customerDue = (float) $receivables->sum('balance');
        $payablesAndLoans = (float) $payables->sum('balance');

        $periodJournal = $this->journalTotalsByType($companyId, $window['start'], $window['end']);
        $previousJournal = $this->journalTotalsByType($companyId, $window['previous_start'], $window['previous_end']);
        $periodSales = $this->salesTotal($companyId, $window['start'], $window['end']);
        $previousSales = $this->salesTotal($companyId, $window['previous_start'], $window['previous_end']);
        $periodIncome = $periodJournal['Income']['balance'];
        $periodExpense = $periodJournal['Expense']['balance'];
        $periodProfit = $periodIncome - $periodExpense;

        $cashMovement = $this->cashMovement(
            $companyId,
            $moneyAccounts->pluck('id'),
            $window['start'],
            $window['end'],
        );
        $cashMovement['opening'] = $availableMoney - $cashMovement['movement'];
        $cashMovement['closing'] = $availableMoney;

        $currentStatement = $this->statementSnapshot($accounts, $accountBalances);
        $journalHealth = $this->journalHealth($companyId, $window['start'], $window['end']);
        $invoiceStatus = $this->invoiceStatus($companyId, $window['start'], $window['end']);
        $alerts = $this->alerts($receivables, $payables, $journalHealth);
        $decisions = $this->decisions($availableMoney, $customerDue, $payablesAndLoans, $periodProfit, $journalHealth);

        return [
            'period' => $period,
            'periodLabel' => $window['label'],
            'periodOptions' => $this->periodOptions($window['today']),
            'metrics' => [
                'available_money' => $availableMoney,
                'sales' => $periodSales,
                'sales_change' => $this->percentageChange($periodSales, $previousSales),
                'sales_has_comparison' => $previousSales > 0,
                'profit' => $periodProfit,
                'customer_due' => $customerDue,
                'payables_loans' => $payablesAndLoans,
            ],
            'position' => [
                'cash' => $cashMovement,
                'profit' => [
                    'income' => $periodIncome,
                    'expense' => $periodExpense,
                    'net' => $periodProfit,
                ],
                'owner' => [
                    'investment' => $periodJournal['Equity']['credit'],
                    'withdrawal' => $periodJournal['Equity']['debit'],
                    'net' => $periodJournal['Equity']['balance'],
                ],
            ],
            'alerts' => $alerts,
            'journalHealth' => $journalHealth,
            'recentTransactions' => Transaction::query()
                ->with([
                    'transactionHead',
                    'moneyAccount',
                    'transferToMoneyAccount',
                    'party',
                    'journalEntry.lines.chartOfAccount',
                ])
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->whereBetween('transaction_date', [$window['start']->toDateString(), $window['end']->toDateString()])
                ->latest('transaction_date')
                ->latest('id')
                ->limit(5)
                ->get(),
            'moneyAccounts' => $moneyAccountRows,
            'receivables' => $receivables->sortByDesc('balance')->take(3)->values(),
            'payables' => $payables->sortByDesc('balance')->take(3)->values(),
            'receivableCount' => $receivables->count(),
            'payableCount' => $payables->count(),
            'invoiceStatus' => $invoiceStatus,
            'trend' => $this->sixMonthTrend($companyId, $window['today']),
            'decisions' => $decisions,
            'statement' => [
                'revenue' => $periodIncome,
                'expense' => $periodExpense,
                'net_profit' => $periodProfit,
                'assets' => $currentStatement['assets'],
                'liabilities' => $currentStatement['liabilities'],
                'equity' => $currentStatement['equity_with_profit'],
            ],
            'accountantNote' => $this->accountantNote($availableMoney, $payablesAndLoans, $customerDue),
            'categoryLabels' => $categoryLabels,
        ];
    }

    /**
     * @param array{today: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable, label: string} $window
     * @param array<string, string> $categoryLabels
     * @return array<string, mixed>
     */
    private function emptySummary(string $period, array $window, array $categoryLabels): array
    {
        return [
            'period' => $period,
            'periodLabel' => $window['label'],
            'periodOptions' => $this->periodOptions($window['today']),
            'metrics' => [
                'available_money' => 0.0,
                'sales' => 0.0,
                'sales_change' => 0.0,
                'sales_has_comparison' => false,
                'profit' => 0.0,
                'customer_due' => 0.0,
                'payables_loans' => 0.0,
            ],
            'position' => [
                'cash' => ['opening' => 0.0, 'received' => 0.0, 'paid' => 0.0, 'movement' => 0.0, 'closing' => 0.0],
                'profit' => ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0],
                'owner' => ['investment' => 0.0, 'withdrawal' => 0.0, 'net' => 0.0],
            ],
            'alerts' => collect([
                ['tone' => 'success', 'icon' => '✓', 'title' => 'No accounting alerts', 'detail' => 'Connect this user to a company to load dashboard data.'],
            ]),
            'journalHealth' => ['total' => 0, 'unbalanced' => 0],
            'recentTransactions' => collect(),
            'moneyAccounts' => collect(),
            'receivables' => collect(),
            'payables' => collect(),
            'receivableCount' => 0,
            'payableCount' => 0,
            'invoiceStatus' => collect([
                ['key' => 'paid', 'label' => 'Paid', 'tone' => 'green', 'count' => 0, 'amount' => 0.0],
                ['key' => 'partly_paid', 'label' => 'Partly Paid', 'tone' => 'amber', 'count' => 0, 'amount' => 0.0],
                ['key' => 'unpaid', 'label' => 'Unpaid', 'tone' => 'red', 'count' => 0, 'amount' => 0.0],
            ]),
            'trend' => collect(),
            'decisions' => collect([
                ['title' => 'Connect the user to a company', 'detail' => 'Dashboard decisions will appear after accounting data becomes available.'],
            ]),
            'statement' => ['revenue' => 0.0, 'expense' => 0.0, 'net_profit' => 0.0, 'assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0],
            'accountantNote' => 'No company accounting data is available for this user.',
            'categoryLabels' => $categoryLabels,
        ];
    }

    /**
     * @return array{today: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable, label: string}
     */
    private function periodWindow(string $period): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        return match ($period) {
            'today' => [
                'today' => $today,
                'start' => $today,
                'end' => $today,
                'previous_start' => $today->subDay(),
                'previous_end' => $today->subDay(),
                'label' => 'Today · '.$today->format('d M Y'),
            ],
            'week' => [
                'today' => $today,
                'start' => $today->startOfWeek(),
                'end' => $today->endOfWeek(),
                'previous_start' => $today->subWeek()->startOfWeek(),
                'previous_end' => $today->subWeek()->endOfWeek(),
                'label' => 'This Week · '.$today->startOfWeek()->format('d M').' – '.$today->endOfWeek()->format('d M Y'),
            ],
            'quarter' => [
                'today' => $today,
                'start' => $today->startOfQuarter(),
                'end' => $today->endOfQuarter(),
                'previous_start' => $today->subQuarter()->startOfQuarter(),
                'previous_end' => $today->subQuarter()->endOfQuarter(),
                'label' => 'Q'.$today->quarter.' '.$today->year,
            ],
            default => [
                'today' => $today,
                'start' => $today->startOfMonth(),
                'end' => $today->endOfMonth(),
                'previous_start' => $today->subMonthNoOverflow()->startOfMonth(),
                'previous_end' => $today->subMonthNoOverflow()->endOfMonth(),
                'label' => $today->format('F Y'),
            ],
        };
    }

    /** @return array<string, string> */
    private function periodOptions(CarbonImmutable $today): array
    {
        return [
            'month' => $today->format('F Y'),
            'today' => 'Today',
            'week' => 'This Week',
            'quarter' => 'This Quarter',
        ];
    }

    /**
     * @param EloquentCollection<int, MoneyAccount> $moneyAccounts
     * @param array<int, float> $moneyAccountBalances
     * @return Collection<int, array<string, mixed>>
     */
    private function moneyAccountRows(EloquentCollection $moneyAccounts, array $moneyAccountBalances): Collection
    {
        $rows = $moneyAccounts->map(function (MoneyAccount $moneyAccount) use ($moneyAccountBalances): array {
            $balance = (float) ($moneyAccountBalances[$moneyAccount->id] ?? 0);

            return [
                'id' => $moneyAccount->id,
                'name' => $moneyAccount->name,
                'kind' => $moneyAccount->kind,
                'description' => match ($moneyAccount->kind) {
                    'Cash' => 'For daily cash operations',
                    'Bank' => 'Business bank account',
                    'Digital' => 'Digital collections and payments',
                    default => 'Business money account',
                },
                'balance' => $balance,
                'bar_tone' => match ($moneyAccount->kind) {
                    'Cash' => 'green',
                    'Digital' => 'amber',
                    default => 'blue',
                },
            ];
        });

        $maximum = max(1.0, (float) $rows->max(fn (array $row): float => abs((float) $row['balance'])));

        return $rows->map(function (array $row) use ($maximum): array {
            $row['progress'] = min(100.0, max(0.0, abs((float) $row['balance']) / $maximum * 100));

            return $row;
        })->values();
    }

    /**
     * @param EloquentCollection<int, Party> $parties
     * @param array<int, float> $partyBalances
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function partyRows(EloquentCollection $parties, array $partyBalances): array
    {
        $receivables = collect();
        $payables = collect();

        foreach ($parties as $party) {
            $balance = (float) ($partyBalances[$party->id] ?? 0);

            if ($balance <= 0) {
                continue;
            }

            if ($party->receivable_account_id && $party->receivableAccount?->type === 'Asset') {
                $receivables->push([
                    'id' => $party->id,
                    'name' => $party->name,
                    'type' => $party->type,
                    'balance' => $balance,
                    'status' => 'Outstanding',
                    'tone' => 'red',
                ]);
            }

            if ($party->payable_account_id && $party->payableAccount?->type === 'Liability') {
                $payables->push([
                    'id' => $party->id,
                    'name' => $party->name,
                    'type' => $party->type,
                    'balance' => $balance,
                    'status' => $party->type === 'Lender' ? 'Loan balance' : 'Payable',
                    'tone' => $party->type === 'Lender' ? 'blue' : 'amber',
                ]);
            }
        }

        return [$receivables, $payables];
    }

    /**
     * @return array<string, array{debit: float, credit: float, balance: float}>
     */
    private function journalTotalsByType(int $companyId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $totals = collect(['Asset', 'Liability', 'Income', 'Expense', 'Equity'])
            ->mapWithKeys(fn (string $type): array => [$type => ['debit' => 0.0, 'credit' => 0.0, 'balance' => 0.0]])
            ->all();

        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.chart_of_account_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('chart_of_accounts.type')
            ->get([
                'chart_of_accounts.type as account_type',
                DB::raw('COALESCE(SUM(journal_lines.debit), 0) as debit_total'),
                DB::raw('COALESCE(SUM(journal_lines.credit), 0) as credit_total'),
            ]);

        foreach ($rows as $row) {
            $type = (string) $row->getAttribute('account_type');
            $debit = (float) $row->getAttribute('debit_total');
            $credit = (float) $row->getAttribute('credit_total');

            if (! array_key_exists($type, $totals)) {
                continue;
            }

            $totals[$type] = [
                'debit' => $debit,
                'credit' => $credit,
                'balance' => in_array($type, ['Liability', 'Income', 'Equity'], true)
                    ? $credit - $debit
                    : $debit - $credit,
            ];
        }

        return $totals;
    }

    private function salesTotal(int $companyId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) Transaction::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->where('category', TransactionTypes::SALE)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }

    /**
     * @param Collection<int, int> $moneyAccountIds
     * @return array{received: float, paid: float, movement: float}
     */
    private function cashMovement(
        int $companyId,
        Collection $moneyAccountIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        if ($moneyAccountIds->isEmpty()) {
            return ['received' => 0.0, 'paid' => 0.0, 'movement' => 0.0];
        }

        $row = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereIn('journal_lines.money_account_id', $moneyAccountIds)
            ->whereBetween('journal_entries.entry_date', [$start->toDateString(), $end->toDateString()])
            ->first([
                DB::raw('COALESCE(SUM(journal_lines.debit), 0) as debit_total'),
                DB::raw('COALESCE(SUM(journal_lines.credit), 0) as credit_total'),
            ]);

        $received = (float) ($row?->getAttribute('debit_total') ?? 0);
        $paid = (float) ($row?->getAttribute('credit_total') ?? 0);

        return ['received' => $received, 'paid' => $paid, 'movement' => $received - $paid];
    }

    /**
     * @param EloquentCollection<int, ChartOfAccount> $accounts
     * @param array<int, float> $balances
     * @return array{assets: float, liabilities: float, equity_with_profit: float}
     */
    private function statementSnapshot(EloquentCollection $accounts, array $balances): array
    {
        $total = function (string $type) use ($accounts, $balances): float {
            return (float) $accounts
                ->where('type', $type)
                ->sum(fn (ChartOfAccount $account): float => (float) ($balances[$account->id] ?? 0));
        };

        $allTimeIncome = $total('Income');
        $allTimeExpense = $total('Expense');

        return [
            'assets' => $total('Asset'),
            'liabilities' => $total('Liability'),
            'equity_with_profit' => $total('Equity') + $allTimeIncome - $allTimeExpense,
        ];
    }

    /** @return array{total: int, unbalanced: int} */
    private function journalHealth(int $companyId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $entries = JournalEntry::query()
            ->withSum('lines as debit_total', 'debit')
            ->withSum('lines as credit_total', 'credit')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $unbalanced = $entries->filter(function (JournalEntry $entry): bool {
            return abs((float) $entry->getAttribute('debit_total') - (float) $entry->getAttribute('credit_total')) > 0.005;
        })->count();

        return ['total' => $entries->count(), 'unbalanced' => $unbalanced];
    }

    /** @return Collection<int, array{key:string,label:string,tone:string,count:int,amount:float}> */
    private function invoiceStatus(int $companyId, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $summary = [
            'paid' => ['key' => 'paid', 'label' => 'Paid', 'tone' => 'green', 'count' => 0, 'amount' => 0.0],
            'partly_paid' => ['key' => 'partly_paid', 'label' => 'Partly Paid', 'tone' => 'amber', 'count' => 0, 'amount' => 0.0],
            'unpaid' => ['key' => 'unpaid', 'label' => 'Unpaid', 'tone' => 'red', 'count' => 0, 'amount' => 0.0],
        ];

        $sales = Transaction::query()
            ->with(['journalEntry.lines' => fn ($query) => $query
                ->select(['id', 'journal_entry_id', 'money_account_id', 'debit', 'credit'])])
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->where('category', TransactionTypes::SALE)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        foreach ($sales as $sale) {
            $key = match ($sale->settlement_type) {
                TransactionTypes::CREDIT => 'unpaid',
                TransactionTypes::PARTIAL => 'partly_paid',
                default => 'paid',
            };
            $summary[$key]['count']++;
            $summary[$key]['amount'] += (float) $sale->amount;
        }

        return collect(array_values($summary));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function sixMonthTrend(int $companyId, CarbonImmutable $today): Collection
    {
        $months = collect(range(5, 0))->map(fn (int $monthsAgo): CarbonImmutable => $today->subMonthsNoOverflow($monthsAgo)->startOfMonth());
        $firstMonth = $months->first();
        $lastMonth = $months->last();

        if (! $firstMonth instanceof CarbonImmutable || ! $lastMonth instanceof CarbonImmutable) {
            return collect();
        }

        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.chart_of_account_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereIn('chart_of_accounts.type', ['Income', 'Expense'])
            ->whereBetween('journal_entries.entry_date', [$firstMonth->toDateString(), $lastMonth->endOfMonth()->toDateString()])
            ->groupBy('journal_entries.entry_date', 'chart_of_accounts.type')
            ->get([
                'journal_entries.entry_date as entry_date',
                'chart_of_accounts.type as account_type',
                DB::raw('COALESCE(SUM(journal_lines.debit), 0) as debit_total'),
                DB::raw('COALESCE(SUM(journal_lines.credit), 0) as credit_total'),
            ]);

        $trend = $months->map(function (CarbonImmutable $month) use ($rows): array {
            $monthRows = $rows->filter(function (JournalLine $row) use ($month): bool {
                return CarbonImmutable::parse((string) $row->getAttribute('entry_date'))->format('Y-m') === $month->format('Y-m');
            });

            $income = 0.0;
            $expense = 0.0;

            foreach ($monthRows as $row) {
                $debit = (float) $row->getAttribute('debit_total');
                $credit = (float) $row->getAttribute('credit_total');

                if ($row->getAttribute('account_type') === 'Income') {
                    $income += $credit - $debit;
                } else {
                    $expense += $debit - $credit;
                }
            }

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'sales' => $income,
                'expense' => $expense,
            ];
        });

        $maximum = max(1.0, (float) $trend->max(fn (array $row): float => max((float) $row['sales'], (float) $row['expense'])));

        return $trend->map(function (array $row) use ($maximum): array {
            $row['sales_height'] = (float) $row['sales'] > 0 ? max(5.0, (float) $row['sales'] / $maximum * 100) : 0.0;
            $row['expense_height'] = (float) $row['expense'] > 0 ? max(5.0, (float) $row['expense'] / $maximum * 100) : 0.0;

            return $row;
        })->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $receivables
     * @param Collection<int, array<string, mixed>> $payables
     * @param array{total: int, unbalanced: int} $journalHealth
     * @return Collection<int, array<string, string>>
     */
    private function alerts(Collection $receivables, Collection $payables, array $journalHealth): Collection
    {
        $alerts = collect();
        $receivableTotal = (float) $receivables->sum('balance');
        $payableTotal = (float) $payables->sum('balance');

        if ($receivableTotal > 0) {
            $alerts->push([
                'tone' => 'warning',
                'icon' => '!',
                'title' => $receivables->count().' customer balance'.($receivables->count() === 1 ? '' : 's').' need follow-up',
                'detail' => CompanyContext::money($receivableTotal).' remains receivable.',
            ]);
        }

        if ($payableTotal > 0) {
            $alerts->push([
                'tone' => 'warning',
                'icon' => '!',
                'title' => $payables->count().' supplier or lender balance'.($payables->count() === 1 ? '' : 's').' need planning',
                'detail' => CompanyContext::money($payableTotal).' remains payable.',
            ]);
        }

        $alerts->push($journalHealth['unbalanced'] > 0
            ? [
                'tone' => 'danger',
                'icon' => '!',
                'title' => $journalHealth['unbalanced'].' unbalanced journal'.($journalHealth['unbalanced'] === 1 ? '' : 's').' found',
                'detail' => 'Review posted debit and credit totals before reporting.',
            ]
            : [
                'tone' => 'success',
                'icon' => '✓',
                'title' => 'All journals are balanced',
                'detail' => 'Debit and credit matched for '.$journalHealth['total'].' posted '.($journalHealth['total'] === 1 ? 'entry' : 'entries').' in this period.',
            ]);

        if ($alerts->isEmpty()) {
            $alerts->push([
                'tone' => 'success',
                'icon' => '✓',
                'title' => 'No accounting alerts',
                'detail' => 'No outstanding balances or journal exceptions were found.',
            ]);
        }

        return $alerts;
    }

    /**
     * @param array{total: int, unbalanced: int} $journalHealth
     * @return Collection<int, array{title: string, detail: string}>
     */
    private function decisions(
        float $availableMoney,
        float $customerDue,
        float $payablesAndLoans,
        float $periodProfit,
        array $journalHealth,
    ): Collection {
        return collect([
            [
                'title' => $customerDue > 0 ? 'Collect customer dues before extending more credit' : 'Customer collection position is clear',
                'detail' => $customerDue > 0
                    ? 'Outstanding customer balance is '.CompanyContext::money($customerDue).'.'
                    : 'No positive customer receivable balance is currently outstanding.',
            ],
            [
                'title' => $payablesAndLoans > $availableMoney ? 'Protect operating cash before paying all liabilities' : 'Schedule supplier and lender payments',
                'detail' => 'Available money is '.CompanyContext::money($availableMoney).' against '.CompanyContext::money($payablesAndLoans).' payable and loan balance.',
            ],
            [
                'title' => $periodProfit >= 0 ? 'Use the positive profit carefully' : 'Review expenses and pricing immediately',
                'detail' => 'Net profit preview for '.$this->signedLabel($periodProfit).' is '.CompanyContext::money(abs($periodProfit)).'.',
            ],
            [
                'title' => $journalHealth['unbalanced'] > 0 ? 'Resolve journal exceptions before reporting' : 'Posted journals are ready for review',
                'detail' => $journalHealth['unbalanced'] > 0
                    ? $journalHealth['unbalanced'].' posted journal entries are not balanced.'
                    : 'All posted journal entries in the selected period have matching debit and credit totals.',
            ],
        ]);
    }

    private function accountantNote(float $availableMoney, float $payablesAndLoans, float $customerDue): string
    {
        if ($payablesAndLoans > $availableMoney) {
            return 'Available money is below the current supplier and lender balance. Prioritize collections and schedule payments before owner withdrawals.';
        }

        if ($customerDue > 0) {
            return 'Money covers the current payable and loan balance, but customer collections should still be followed up before new credit sales.';
        }

        return 'Current money is sufficient for recorded obligations and there are no positive customer dues requiring follow-up.';
    }

    private function percentageChange(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100;
    }

    private function signedLabel(float $value): string
    {
        return $value >= 0 ? 'the selected period' : 'the selected period (loss)';
    }
}
