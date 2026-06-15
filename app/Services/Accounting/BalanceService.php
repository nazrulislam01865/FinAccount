<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Party;

class BalanceService
{
    public function __construct(
        private readonly ChartOfAccountBalanceService $accountBalanceService,
        private readonly PartyService $partyService,
    ) {}

    /** @return array<string, mixed> */
    public function pageData(int $companyId): array
    {
        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        $parties = Party::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        return [
            'accounts' => $accounts,
            'accountBalances' => $this->accountBalanceService->balancesFor($accounts, $companyId),
            'parties' => $parties,
            'partyBalances' => $this->partyService->balancesFor($parties, $companyId),
        ];
    }
}
