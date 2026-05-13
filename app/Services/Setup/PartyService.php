<?php

namespace App\Services\Setup;

use App\Models\Company;
use App\Models\Party;

class PartyService
{
    public function create(array $data, ?int $userId = null): Party
    {
        $company = Company::query()->first();

        $data['company_id'] = $company?->id;
        $data['party_code'] = $this->nextPartyCode();
        $data['sub_type'] = $data['sub_type'] ?? null;
        $data['default_ledger_nature'] = $data['default_ledger_nature'] ?? null;
        $data['sub_type'] = $data['sub_type'] ?? null;
        $data['default_ledger_nature'] = $data['default_ledger_nature'] ?? null;
        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        // The PRD captures only an amount here; debit/credit sides belong to Opening Balance Setup.
        $data['opening_balance_type'] = null;

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return Party::query()->create($data);
    }

    public function update(Party $party, array $data, ?int $userId = null): Party
    {
        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        // The PRD captures only an amount here; debit/credit sides belong to Opening Balance Setup.
        $data['opening_balance_type'] = null;

        $data['updated_by'] = $userId;

        $party->update($data);

        return $party->fresh(['partyType', 'linkedLedger']);
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
}
