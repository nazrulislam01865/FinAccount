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
        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        if ((float) $data['opening_balance'] <= 0) {
            $data['opening_balance_type'] = null;
        }

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return Party::query()->create($data);
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
