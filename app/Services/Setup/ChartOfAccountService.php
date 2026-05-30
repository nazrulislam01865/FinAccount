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

        return $account->fresh(['accountType', 'parent', 'partyType']);
    }

    private function prepareAccountingData(array $data): array
    {
        $accountType = AccountType::query()->find($data['account_type_id'] ?? null);
        $coaLevel = (int) ($data['coa_level'] ?? 4);
        $isPostingLevel = $coaLevel === 4;
        $ledgerType = $isPostingLevel ? ($data['ledger_type'] ?? 'Asset') : 'Group';
        $isPartyControl = $ledgerType === 'Party Control';

        $data['parent_id'] = $coaLevel === 1 ? null : ($data['parent_id'] ?? null);
        $data['coa_level'] = $coaLevel;
        $data['account_level'] = $isPostingLevel ? 'Ledger' : 'Group';
        $data['account_nature'] = $accountType?->name ?? $data['account_nature'] ?? null;
        $data['normal_balance'] = $data['normal_balance']
            ?? $accountType?->normal_balance
            ?? null;
        $data['ledger_type'] = $ledgerType;

        // SRS rule: only Level 4 ledger heads can receive journal/voucher postings.
        $data['posting_allowed'] = $isPostingLevel;

        // Cash/bank and party-control are derived from Ledger Type to avoid inconsistent setup.
        $data['is_cash_bank'] = $isPostingLevel && in_array($ledgerType, ['Cash', 'Bank'], true);
        $data['is_party_control'] = $isPostingLevel && $isPartyControl;
        $data['party_type_id'] = $isPartyControl ? ($data['party_type_id'] ?? null) : null;

        // Party control ledgers are selected through party/rule logic, not ordinary user dropdowns.
        $data['is_user_selectable'] = $isPostingLevel
            && ! $isPartyControl
            && (bool) ($data['is_user_selectable'] ?? true);

        $data['is_system_ledger'] = (bool) ($data['is_system_ledger'] ?? false);

        $classification = $this->resolveClassification($data);
        $data['account_group'] = $classification['account_group'];
        $data['account_sub_group'] = $classification['account_sub_group'];

        unset($data['opening_balance']);

        return $data;
    }

    private function resolveClassification(array $data): array
    {
        $level = (int) ($data['coa_level'] ?? 4);
        $accountName = (string) ($data['account_name'] ?? '');
        $parent = ! empty($data['parent_id'])
            ? ChartOfAccount::query()->with('parent.parent')->find($data['parent_id'])
            : null;

        if ($level === 1) {
            return ['account_group' => null, 'account_sub_group' => null];
        }

        if ($level === 2) {
            return ['account_group' => $accountName, 'account_sub_group' => null];
        }

        if ($level === 3) {
            return [
                'account_group' => $parent?->account_name,
                'account_sub_group' => $accountName,
            ];
        }

        return [
            'account_group' => $parent?->parent?->account_name ?? $data['account_group'] ?? null,
            'account_sub_group' => $parent?->account_name ?? $data['account_sub_group'] ?? null,
        ];
    }
}
