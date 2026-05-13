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

        $data['company_id'] = $company?->id;
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['account_level'] = $data['account_level'] ?? 'Ledger';
        $data['normal_balance'] = $data['normal_balance']
            ?? $this->normalBalanceForType($data['account_type_id'] ?? null);
        $data['posting_allowed'] = $data['account_level'] === 'Group' ? false : (bool) ($data['posting_allowed'] ?? true);
        $data['is_cash_bank'] = (bool) ($data['is_cash_bank'] ?? false);
        // Opening balances are stored through OpeningBalanceService, not Chart of Accounts.
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return ChartOfAccount::query()->create($data);
    }

    public function update(ChartOfAccount $account, array $data, ?int $userId = null): ChartOfAccount
    {
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['account_level'] = $data['account_level'] ?? 'Ledger';
        $data['normal_balance'] = $data['normal_balance']
            ?? $account->normal_balance
            ?? $this->normalBalanceForType($data['account_type_id'] ?? null);
        $data['posting_allowed'] = $data['account_level'] === 'Group' ? false : (bool) ($data['posting_allowed'] ?? true);
        $data['is_cash_bank'] = (bool) ($data['is_cash_bank'] ?? false);
        // Opening balances are stored through OpeningBalanceService, not Chart of Accounts.
        $data['updated_by'] = $userId;

        $account->update($data);

        return $account->fresh(['accountType', 'parent']);
    }

    private function normalBalanceForType(?int $accountTypeId): ?string
    {
        if (!$accountTypeId) {
            return null;
        }

        return AccountType::query()->whereKey($accountTypeId)->value('normal_balance');
    }
}
