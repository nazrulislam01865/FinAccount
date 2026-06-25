<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Support\TransactionTypes;

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
            ->where('is_active', true)
            ->whereNotNull('chart_of_account_id')
            ->pluck('chart_of_account_id');

        $cash = (float) $moneyAccountIds->sum(fn (int $accountId): float => (float) ($balances[$accountId] ?? 0));

        // Historical cash classifications must come from the journal lines that
        // were stored at posting time. They must not change if a rule is edited later.
        $salesCollected = (float) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('transactions.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('transactions.status', 'posted')
            ->whereIn('transactions.category', [TransactionTypes::SALE, TransactionTypes::CUSTOMER_COLLECTION])
            ->whereNotNull('journal_lines.money_account_id')
            ->sum('journal_lines.debit');

        $paymentsMade = (float) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('transactions.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('transactions.status', 'posted')
            ->whereIn('transactions.category', [
                TransactionTypes::PURCHASE,
                TransactionTypes::SUPPLIER_PAYMENT,
                TransactionTypes::EXPENSE,
                TransactionTypes::OWNER_WITHDRAWAL,
                TransactionTypes::LOAN_REPAYMENT,
                TransactionTypes::LOAN_INTEREST_PAYMENT,
                TransactionTypes::ASSET_PURCHASE,
            ])
            ->whereNotNull('journal_lines.money_account_id')
            ->sum('journal_lines.credit');

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
