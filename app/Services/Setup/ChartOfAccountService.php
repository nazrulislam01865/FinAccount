<?php

namespace App\Services\Setup;

use App\Models\ChartOfAccount;
use App\Models\Company;

class ChartOfAccountService
{
    public function create(array $data, ?int $userId = null): ChartOfAccount
    {
        $company = Company::query()->first();

        $data['company_id'] = $company?->id;
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['is_cash_bank'] = (bool) ($data['is_cash_bank'] ?? false);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return ChartOfAccount::query()->create($data);
    }

    public function update(ChartOfAccount $account, array $data, ?int $userId = null): ChartOfAccount
    {
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['is_cash_bank'] = (bool) ($data['is_cash_bank'] ?? false);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['updated_by'] = $userId;

        $account->update($data);

        return $account->fresh(['accountType', 'parent']);
    }
}
