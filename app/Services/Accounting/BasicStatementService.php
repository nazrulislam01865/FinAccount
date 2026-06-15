<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\MoneyAccount;
use App\Models\Transaction;

class BasicStatementService
{
    public function __construct(private readonly ChartOfAccountBalanceService $balanceService) {}

    /** @return array<string, float> */
    public function summary(int $companyId): array
    {
        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->get();
        $balances = $this->balanceService->balancesFor($accounts, $companyId);

        $total = function (string $type) use ($accounts, $balances): float {
            return (float) $accounts
                ->where('type', $type)
                ->sum(fn (ChartOfAccount $account): float => (float) ($balances[$account->id] ?? 0));
        };

        $income = $total('Income');
        $expense = $total('Expense');
        $asset = $total('Asset');
        $liability = $total('Liability');
        $equity = $total('Equity');
        $net = $income - $expense;

        $moneyAccountIds = MoneyAccount::query()
            ->where('company_id', $companyId)
            ->pluck('chart_of_account_id');

        $cash = (float) $moneyAccountIds->sum(fn (int $accountId): float => (float) ($balances[$accountId] ?? 0));

        $salesCollected = (float) Transaction::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->where('category', 'Sales')
            ->whereNotNull('money_account_id')
            ->sum('amount');

        $paymentsMade = (float) Transaction::query()
            ->where('transactions.company_id', $companyId)
            ->where('transactions.status', 'posted')
            ->whereNotNull('transactions.money_account_id')
            ->whereIn('transactions.category', ['Payment', 'Liability'])
            ->whereHas('transactionHead.accountingRule', fn ($query) => $query->where('credit_source', AccountingRule::SOURCE_SELECTED_MONEY))
            ->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
            'asset' => $asset,
            'liability' => $liability,
            'equity_with_profit' => $equity + $net,
            'cash' => $cash,
            'sales_collected' => $salesCollected,
            'payments_made' => $paymentsMade,
        ];
    }
}
