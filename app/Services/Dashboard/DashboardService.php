<?php

namespace App\Services\Dashboard;

use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class DashboardService
{
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
            ->get(['id', 'chart_of_account_id', 'opening_balance']);

        $accountIds = $moneyAccounts->pluck('chart_of_account_id');
        $openingBalance = (float) $moneyAccounts->sum('opening_balance');

        $journalMovement = (float) JournalLine::query()
            ->where('company_id', $companyId)
            ->whereIn('chart_of_account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as movement')
            ->value('movement');

        return [
            'metrics' => [
                'sales' => (float) ($categoryTotals['Sales'] ?? 0),
                'payments' => (float) ($categoryTotals['Payment'] ?? 0),
                'liability' => (float) ($categoryTotals['Liability'] ?? 0),
                'money_balance' => $openingBalance + $journalMovement,
            ],
            'recentTransactions' => Transaction::query()
                ->with('transactionHead')
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->latest('transaction_date')
                ->latest('id')
                ->limit(7)
                ->get(),
        ];
    }
}
