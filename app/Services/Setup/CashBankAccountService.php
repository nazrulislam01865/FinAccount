<?php

namespace App\Services\Setup;

use App\Models\Bank;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class CashBankAccountService
{
    public function create(array $data, ?int $userId = null, ?int $companyId = null): CashBankAccount
    {
        return DB::transaction(function () use ($data, $userId, $companyId): CashBankAccount {
            $companyId = $this->resolveCompanyId($companyId);

            // Serializes code generation per company and prevents duplicate IDs
            // when two users save at the same time.
            if ($companyId > 0) {
                Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            }

            $data = $this->prepareAccountingData($data);
            $data['company_id'] = $companyId ?: null;
            $data['cash_bank_code'] = $this->nextCashBankCode($data['type'], $companyId);
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;

            return CashBankAccount::query()
                ->create($data)
                ->fresh(['linkedLedger.accountType', 'bank']);
        });
    }

    public function update(CashBankAccount $account, array $data, ?int $userId = null): CashBankAccount
    {
        return DB::transaction(function () use ($account, $data, $userId): CashBankAccount {
            $data = $this->prepareAccountingData($data, $account);

            // Business ID and company ownership are immutable.
            unset($data['cash_bank_code'], $data['company_id']);

            if (! $account->cash_bank_code) {
                $companyId = (int) ($account->company_id ?? 0);

                if ($companyId > 0) {
                    Company::query()->whereKey($companyId)->lockForUpdate()->first();
                }

                $data['cash_bank_code'] = $this->nextCashBankCode(
                    (string) ($account->type ?: $data['type']),
                    $companyId
                );
            }

            $data['updated_by'] = $userId;
            $account->update($data);

            return $account->fresh(['linkedLedger.accountType', 'bank']);
        });
    }

    private function prepareAccountingData(array $data, ?CashBankAccount $account = null): array
    {
        $type = $data['type'] ?? $account?->type ?? 'Cash';
        $linkedLedgerId = $data['linked_ledger_account_id'] ?? $account?->linked_ledger_account_id;
        $linkedLedger = $linkedLedgerId
            ? ChartOfAccount::query()->find($linkedLedgerId)
            : null;

        $bankId = array_key_exists('bank_id', $data)
            ? $data['bank_id']
            : $account?->bank_id;
        $bank = $bankId ? Bank::query()->find($bankId) : null;

        $cashBankName = trim((string) ($data['cash_bank_name'] ?? $account?->cash_bank_name ?? ''));

        if ($cashBankName === '' && $linkedLedger) {
            $cashBankName = $linkedLedger->account_name;
        }

        $data['cash_bank_name'] = $cashBankName;
        $data['type'] = $type;
        $data['linked_ledger_account_id'] = $linkedLedgerId;
        $data['bank_id'] = $bankId;
        $data['bank_name'] = array_key_exists('bank_name', $data)
            ? $data['bank_name']
            : ($account?->bank_name ?? $bank?->bank_name);
        $data['branch_name'] = array_key_exists('branch_name', $data)
            ? $data['branch_name']
            : $account?->branch_name;
        $data['account_number'] = array_key_exists('account_number', $data)
            ? $data['account_number']
            : $account?->account_number;
        $data['opening_balance'] = array_key_exists('opening_balance', $data)
            ? round((float) $data['opening_balance'], 2)
            : round((float) ($account?->opening_balance ?? 0), 2);
        $data['usage_note'] = array_key_exists('usage_note', $data)
            ? $data['usage_note']
            : $account?->usage_note;
        $data['status'] = $data['status'] ?? $account?->status ?? 'Active';

        if ($type === 'Cash') {
            $data['bank_id'] = null;
            $data['bank_name'] = null;
            $data['branch_name'] = null;
            $data['account_number'] = null;
        }

        if ($type === 'Mobile Banking') {
            $data['bank_id'] = null;
            $data['branch_name'] = null;
        }

        return $data;
    }

    private function nextCashBankCode(string $type, int $companyId): string
    {
        $prefix = match ($type) {
            'Bank' => 'BK',
            'Mobile Banking' => 'MB',
            default => 'CB',
        };

        $numbers = CashBankAccount::query()
            ->withTrashed()
            ->when($companyId > 0,
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->whereNull('company_id'))
            ->where('cash_bank_code', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->pluck('cash_bank_code')
            ->map(function (?string $code) use ($prefix): int {
                if (! $code || ! preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            });

        $next = ((int) $numbers->max()) + 1;

        return $prefix . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function resolveCompanyId(?int $companyId): int
    {
        return (int) ($companyId ?: Company::query()->orderBy('id')->value('id') ?: 0);
    }
}
