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
        $data['cash_bank_code'] = $data['cash_bank_code'] ?? $this->nextCashBankCode($data['type'] ?? 'Cash');
        $data['bank_id'] = $data['bank_id'] ?? null;
        $data['bank_name'] = $data['bank_name'] ?? null;
        $data['branch_name'] = $data['branch_name'] ?? null;
        $data['account_number'] = $data['account_number'] ?? null;
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['usage_note'] = $data['usage_note'] ?? null;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        if ($data['type'] === 'Cash') {
            $data['bank_id'] = null;
            $data['bank_name'] = null;
            $data['branch_name'] = null;
            $data['account_number'] = null;
        }

        return CashBankAccount::query()->create($data);
    }

    public function update(CashBankAccount $account, array $data, ?int $userId = null): CashBankAccount
    {
        $data['cash_bank_code'] = $data['cash_bank_code'] ?? $account->cash_bank_code ?? $this->nextCashBankCode($data['type'] ?? $account->type);
        $data['bank_id'] = $data['bank_id'] ?? null;
        $data['bank_name'] = $data['bank_name'] ?? null;
        $data['branch_name'] = $data['branch_name'] ?? null;
        $data['account_number'] = $data['account_number'] ?? null;
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['usage_note'] = $data['usage_note'] ?? null;
        $data['updated_by'] = $userId;

        if ($data['type'] === 'Cash') {
            $data['bank_id'] = null;
            $data['bank_name'] = null;
            $data['branch_name'] = null;
            $data['account_number'] = null;
        }

        $account->update($data);

        return $account->fresh(['linkedLedger', 'bank']);
    }

    private function nextCashBankCode(string $type): string
    {
        $prefix = match ($type) {
            'Bank' => 'BK',
            'Mobile Banking' => 'MB',
            default => 'CB',
        };

        $lastCode = CashBankAccount::query()
            ->withTrashed()
            ->where('cash_bank_code', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('cash_bank_code');

        $number = $lastCode ? (int) str_replace($prefix . '-', '', $lastCode) : 0;

        return $prefix . '-' . str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT);
    }
}
