<?php

namespace App\Services\Setup;

use App\Models\CashBankAccount;
use App\Models\Company;

class CashBankAccountService
{
    public function create(array $data, ?int $userId = null): CashBankAccount
    {
        $company = Company::query()->first();

        $data['company_id'] = $company?->id;
        $data['bank_id'] = $data['bank_id'] ?? null;
        $data['branch_name'] = $data['branch_name'] ?? null;
        $data['account_number'] = $data['account_number'] ?? null;
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        if ($data['type'] === 'Cash') {
            $data['bank_id'] = null;
            $data['branch_name'] = null;
            $data['account_number'] = null;
        }

        return CashBankAccount::query()->create($data);
    }
}
