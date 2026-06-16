<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Party;
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

        $balances = [];

        foreach ($accounts as $account) {
            $natural = (float) ($journalMovement[$account->id] ?? 0)
                + (float) ($moneyOpening[$account->id] ?? 0)
                + (float) ($receivableOpening[$account->id] ?? 0)
                - (float) ($payableOpening[$account->id] ?? 0);

            $balances[$account->id] = $account->normal_balance === 'Credit'
                ? -$natural
                : $natural;
        }

        return $balances;
    }
}
