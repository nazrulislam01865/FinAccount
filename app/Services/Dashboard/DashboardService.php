<?php

namespace App\Services\Dashboard;

use App\Models\AccountingOption;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Services\Accounting\AccountingOptionService;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private readonly AccountingOptionService $optionService) {}

    /**
     * @return array{metrics: array<string, float>, recentTransactions: Collection<int, Transaction>}
     */
    public function summary(?int $companyId): array
    {
        if (! $companyId) {
            return [
                'metrics' => [
                    'sales' => 0,
                    'payments' => 0,
                    'liability' => 0,
                    'money_balance' => 0,
                ],
                'recentTransactions' => collect(),
                'categoryLabels' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY),
            ];
        }

        $categoryTotals = Transaction::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $moneyAccounts = MoneyAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('chart_of_account_id')
            ->get(['id', 'chart_of_account_id', 'opening_balance']);

        $accountIds = $moneyAccounts->pluck('chart_of_account_id');
        $openingBalance = (float) $moneyAccounts->sum('opening_balance');

        $journalMovement = (float) JournalLine::query()
            ->where('company_id', $companyId)
            ->whereIn('chart_of_account_id', $accountIds)
            ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as movement')
            ->value('movement');

        return [
            'metrics' => [
                'sales' => (float) ($categoryTotals['Sales'] ?? 0),
                'payments' => (float) ($categoryTotals['Payment'] ?? 0),
                'liability' => (float) ($categoryTotals['Liability'] ?? 0),
                'money_balance' => $openingBalance + $journalMovement,
            ],
            'categoryLabels' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY),
            'recentTransactions' => Transaction::query()
                ->with('transactionHead')
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->latest('id')
                ->limit(7)
                ->get(),
        ];
    }
}
