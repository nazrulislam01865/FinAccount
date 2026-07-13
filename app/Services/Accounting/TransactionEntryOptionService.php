<?php

namespace App\Services\Accounting;

use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Support\TransactionTypes;
use Illuminate\Support\Collection;

class TransactionEntryOptionService
{
    /** @return Collection<int, TransactionHead> */
    public function transactionHeads(int $companyId, string $category): Collection
    {
        $postingTypes = TransactionTypes::postingTypes($category);

        return TransactionHead::query()
            ->with('postingAccount')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(category) = ?', [strtolower($category)])
            ->where('is_active', true)
            ->where('code', 'not like', 'SYS-FEED-%')
            ->whereNotNull('posting_account_id')
            ->whereHas('postingAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($postingTypes !== [], fn ($query) => $query->whereIn('type', $postingTypes)))
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
