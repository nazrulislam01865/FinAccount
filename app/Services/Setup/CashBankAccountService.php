<?php

namespace App\Services\Setup;

use App\Models\Bank;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;

class CashBankAccountService
{
    public function create(array $data, ?int $userId = null): CashBankAccount
    {
        $company = Company::query()->first();
        $data = $this->prepareAccountingData($data);

        $data['company_id'] = $company?->id;
        $data['cash_bank_code'] = $data['cash_bank_code'] ?: $this->nextCashBankCode($data['type']);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return CashBankAccount::query()->create($data);
    }

    public function update(CashBankAccount $account, array $data, ?int $userId = null): CashBankAccount
    {
        $data = $this->prepareAccountingData($data, $account);
        $data['cash_bank_code'] = $data['cash_bank_code'] ?: $account->cash_bank_code ?: $this->nextCashBankCode($data['type']);
        $data['updated_by'] = $userId;

        $account->update($data);

        return $account->fresh(['linkedLedger.accountType', 'bank']);
    }

    private function prepareAccountingData(array $data, ?CashBankAccount $account = null): array
    {
        $type = $data['type'] ?? $account?->type ?? 'Cash';
        $linkedLedgerId = $data['linked_ledger_account_id'] ?? $account?->linked_ledger_account_id;
        $linkedLedger = $linkedLedgerId
            ? ChartOfAccount::query()->find($linkedLedgerId)
            : null;

        $bankId = $data['bank_id'] ?? null;
        $bank = $bankId ? Bank::query()->find($bankId) : null;

        $cashBankName = trim((string) ($data['cash_bank_name'] ?? ''));

        if ($cashBankName === '' && $linkedLedger) {
            $cashBankName = $linkedLedger->account_name;
        }

        $data['cash_bank_name'] = $cashBankName;
        $data['type'] = $type;
        $data['linked_ledger_account_id'] = $linkedLedgerId;
        $data['bank_id'] = $bankId;
        $data['bank_name'] = $data['bank_name'] ?? $bank?->bank_name ?? null;
        $data['branch_name'] = $data['branch_name'] ?? null;
        $data['account_number'] = $data['account_number'] ?? null;
        $data['opening_balance'] = round((float) ($data['opening_balance'] ?? 0), 2);
        $data['usage_note'] = $data['usage_note'] ?? null;
        $data['status'] = $data['status'] ?? 'Active';

        // Cash boxes do not have bank-specific identity fields.
        if ($type === 'Cash') {
            $data['bank_id'] = null;
            $data['bank_name'] = null;
            $data['branch_name'] = null;
            $data['account_number'] = null;
        }

        // Mobile wallets do not normally have branches. Keep account number for wallet/account ID.
        if ($type === 'Mobile Banking') {
            $data['bank_id'] = null;
            $data['branch_name'] = null;
        }

        return $data;
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