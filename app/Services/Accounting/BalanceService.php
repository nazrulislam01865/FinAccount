<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use App\Models\Party;
use Illuminate\Database\Eloquent\Builder;

class BalanceService
{
    public function __construct(
        private readonly ChartOfAccountBalanceService $accountBalanceService,
        private readonly PartyService $partyService,
        private readonly AccountingOptionService $optionService,
    ) {}

    /** @return array<string, mixed> */
    public function pageData(int $companyId, string $partySearch = ''): array
    {
        $partySearch = trim($partySearch);

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        $parties = Party::query()
            ->where('company_id', $companyId)
            ->when($partySearch !== '', function (Builder $query) use ($partySearch): void {
                $needle = '%'.$partySearch.'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query->where('code', 'like', $needle)
                        ->orWhere('name', 'like', $needle)
                        ->orWhere('type', 'like', $needle)
                        ->orWhere('phone', 'like', $needle)
                        ->orWhere('email', 'like', $needle);
                });
            })
            ->orderBy('code')
            ->get();

        return [
            'accounts' => $accounts,
            'accountBalances' => $this->accountBalanceService->balancesFor($accounts, $companyId),
            'parties' => $parties,
            'partyBalances' => $this->partyService->balancesFor($parties, $companyId),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_PARTY_TYPE),
            'partySearch' => $partySearch,
        ];
    }
}
