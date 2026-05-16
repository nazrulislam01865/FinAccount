<?php

namespace App\Services\Setup;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Party;
use App\Models\PartyType;

class PartyService
{
    public function create(array $data, ?int $userId = null): Party
    {
        $company = Company::query()->first();
        $data = $this->prepareAccountingData($data);

        $data['company_id'] = $company?->id;
        $data['party_code'] = $this->nextPartyCode();
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return Party::query()->create($data);
    }

    public function update(Party $party, array $data, ?int $userId = null): Party
    {
        $data = $this->prepareAccountingData($data, $party);
        $data['updated_by'] = $userId;

        $party->update($data);

        return $party->fresh(['partyType.defaultLedger.accountType', 'linkedLedger.accountType']);
    }

    private function prepareAccountingData(array $data, ?Party $party = null): array
    {
        $partyType = isset($data['party_type_id'])
            ? PartyType::query()->find($data['party_type_id'])
            : $party?->partyType;

        $ledgerId = $data['linked_ledger_account_id']
            ?? $partyType?->default_ledger_account_id
            ?? $party?->linked_ledger_account_id;

        $ledger = $ledgerId
            ? ChartOfAccount::query()->with('accountType')->find($ledgerId)
            : null;

        $nature = $data['default_ledger_nature']
            ?? $this->inferLedgerNatureFromPartyType($partyType)
            ?? $party?->default_ledger_nature
            ?? 'No Effect';

        $openingBalance = $this->amount($data['opening_balance'] ?? 0);

        $data['party_name'] = trim((string) ($data['party_name'] ?? $party?->party_name));
        $data['party_type_id'] = $data['party_type_id'] ?? $party?->party_type_id;
        $data['sub_type'] = $data['sub_type'] ?? null;
        $data['mobile'] = $data['mobile'] ?? null;
        $data['email'] = $data['email'] ?? null;
        $data['address'] = $data['address'] ?? null;
        $data['linked_ledger_account_id'] = $ledgerId;
        $data['default_ledger_nature'] = $nature;
        $data['opening_balance'] = $openingBalance;
        $data['opening_balance_type'] = $openingBalance > 0
            ? $this->openingSideFromLedgerNature($nature, $ledger)
            : null;
        $data['notes'] = $data['notes'] ?? null;
        $data['status'] = $data['status'] ?? 'Active';

        return $data;
    }

    private function nextPartyCode(): string
    {
        $lastCode = Party::query()
            ->withTrashed()
            ->whereNotNull('party_code')
            ->orderByDesc('id')
            ->value('party_code');

        if (!$lastCode) {
            return 'P-00001';
        }

        $number = (int) str_replace('P-', '', $lastCode);

        return 'P-' . str_pad((string) ($number + 1), 5, '0', STR_PAD_LEFT);
    }

    private function amount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.00;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }

    private function inferLedgerNatureFromPartyType(?PartyType $partyType): string
    {
        $code = strtoupper((string) $partyType?->code);
        $name = strtoupper((string) $partyType?->name);
        $value = $code . ' ' . $name;

        if (str_contains($value, 'CUSTOMER') || str_contains($value, 'CUS') || str_contains($value, 'TENANT')) {
            return 'Receivable';
        }

        if (
            str_contains($value, 'SUPPLIER')
            || str_contains($value, 'SUP')
            || str_contains($value, 'VENDOR')
            || str_contains($value, 'LANDLORD')
        ) {
            return 'Payable';
        }

        if (str_contains($value, 'EMPLOYEE') || str_contains($value, 'DRIVER')) {
            return 'Payable';
        }

        if (str_contains($value, 'OWNER')) {
            return 'No Effect';
        }

        return 'No Effect';
    }

    private function openingSideFromLedgerNature(string $nature, ?ChartOfAccount $ledger = null): ?string
    {
        return match ($nature) {
            'Receivable', 'Advance Paid' => 'Debit',
            'Payable', 'Advance Received' => 'Credit',
            default => $ledger?->normal_balance ?: $ledger?->accountType?->normal_balance,
        };
    }
}
