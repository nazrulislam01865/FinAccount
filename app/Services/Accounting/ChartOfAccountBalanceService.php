<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\OpeningBalance;
use Illuminate\Support\Collection;

class ChartOfAccountBalanceService
{
    /**
     * @param Collection<int, ChartOfAccount> $accounts
     * @return array<int, float>
     */
    public function balancesFor(Collection $accounts, int $companyId): array
    {
        $journalMovement = JournalLine::query()
            ->selectRaw('chart_of_account_id, SUM(debit - credit) AS movement')
            ->where('company_id', $companyId)
            ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
            ->groupBy('chart_of_account_id')
            ->pluck('movement', 'chart_of_account_id');

        $openingBalances = OpeningBalance::query()
            ->selectRaw('chart_of_account_id, COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS opening')
            ->where('company_id', $companyId)
            ->where('status', OpeningBalance::STATUS_POSTED)
            ->groupBy('chart_of_account_id')
            ->pluck('opening', 'chart_of_account_id');

        $balances = [];

        foreach ($accounts as $account) {
            $natural = (float) ($journalMovement[$account->id] ?? 0)
                + (float) ($openingBalances[$account->id] ?? 0);

            $balances[$account->id] = $account->normal_balance === 'Credit'
                ? -$natural
                : $natural;
        }

        return $balances;
    }

    /**
     * Return the balance of each individual money account.
     *
     * Multiple bank, cash, or digital accounts may intentionally share the
     * same mapped COA. Their operational balances therefore have to be
     * grouped by money_account_id, not by chart_of_account_id.
     *
     * @param Collection<int, MoneyAccount> $moneyAccounts
     * @return array<int, float>
     */
    public function balancesForMoneyAccounts(Collection $moneyAccounts, int $companyId): array
    {
        $moneyAccountIds = $moneyAccounts
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($moneyAccountIds->isEmpty()) {
            return [];
        }

        $journalMovement = JournalLine::query()
            ->selectRaw('money_account_id, SUM(debit - credit) AS movement')
            ->where('company_id', $companyId)
            ->whereIn('money_account_id', $moneyAccountIds)
            ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
            ->groupBy('money_account_id')
            ->pluck('movement', 'money_account_id');

        $openingBalances = OpeningBalance::query()
            ->selectRaw('money_account_id, COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS opening')
            ->where('company_id', $companyId)
            ->whereIn('money_account_id', $moneyAccountIds)
            ->where('status', OpeningBalance::STATUS_POSTED)
            ->groupBy('money_account_id')
            ->pluck('opening', 'money_account_id');

        $balances = [];

        foreach ($moneyAccounts as $moneyAccount) {
            $natural = (float) ($journalMovement[$moneyAccount->id] ?? 0)
                + (float) ($openingBalances[$moneyAccount->id] ?? 0);

            $balances[$moneyAccount->id] = $moneyAccount->chartOfAccount?->normal_balance === 'Credit'
                ? -$natural
                : $natural;
        }

        return $balances;
    }
}
