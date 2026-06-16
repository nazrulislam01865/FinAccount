<?php

namespace App\Services\Accounting;

use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use Illuminate\Support\Collection;

class TransactionEntryOptionService
{
    /** @return Collection<int, TransactionHead> */
    public function transactionHeads(int $companyId, string $category): Collection
    {
        return TransactionHead::query()
            ->with(['accountingRule', 'postingAccount'])
            ->where('company_id', $companyId)
            ->where('category', $category)
            ->where('is_active', true)
            ->whereNotNull('accounting_rule_id')
            ->whereNotNull('posting_account_id')
            ->whereHas('accountingRule', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('category', $category)
                ->where('is_active', true))
            ->whereHas('postingAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, MoneyAccount> */
    public function moneyAccounts(int $companyId): Collection
    {
        return MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('chart_of_account_id')
            ->whereHas('chartOfAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Party> */
    public function parties(int $companyId): Collection
    {
        return Party::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
