<?php

namespace App\Services\Setup;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Company;

class ChartOfAccountService
{
    public function create(array $data, ?int $userId = null): ChartOfAccount
    {
        $company = Company::query()->first();
        $data = $this->prepareAccountingData($data);

        $data['company_id'] = $company?->id;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return ChartOfAccount::query()->create($data);
    }

    public function update(ChartOfAccount $account, array $data, ?int $userId = null): ChartOfAccount
    {
        $data = $this->prepareAccountingData($data);
        $data['updated_by'] = $userId;

        $account->update($data);

        return $account->fresh(['accountType', 'parent']);
    }

    private function prepareAccountingData(array $data): array
    {
        $accountType = AccountType::query()->find($data['account_type_id'] ?? null);
        $accountLevel = $data['account_level'] ?? 'Ledger';
        $isGroup = $accountLevel === 'Group';
        $isAssetType = $accountType?->name === 'Asset';

        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['account_level'] = $accountLevel;
        $data['normal_balance'] = $accountType?->normal_balance
            ?? $data['normal_balance']
            ?? null;

        // Parent/group accounts are headings only. They must never receive journal lines.
        $data['posting_allowed'] = $isGroup
            ? false
            : (bool) ($data['posting_allowed'] ?? true);

        // Cash/bank ledgers are always Asset + Ledger + posting accounts.
        $data['is_cash_bank'] = !$isGroup
            && $isAssetType
            && (bool) $data['posting_allowed']
            && (bool) ($data['is_cash_bank'] ?? false);

        unset($data['opening_balance']);

        return $data;
    }
}