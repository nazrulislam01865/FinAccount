<?php

namespace App\Services\Setup;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Party;
use App\Models\PartyLedgerMapping;
use App\Models\PartyType;
use App\Support\PartyAccountingProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartyService
{
    public function create(array $data, ?int $userId = null, ?int $companyId = null): Party
    {
        return DB::transaction(function () use ($data, $userId, $companyId): Party {
            $companyId = $companyId
                ?: (int) ($data['company_id'] ?? 0)
                ?: (int) Company::query()->orderBy('id')->value('id');

            [$partyData, $mappings] = $this->prepareData($data);

            $partyData['company_id'] = $companyId ?: null;
            $partyData['party_code'] = $this->nextPartyCode();
            $partyData['created_by'] = $userId;
            $partyData['updated_by'] = $userId;

            $party = Party::query()->create($partyData);
            $this->syncLedgerMappings($party, $mappings, $userId);

            return $this->freshParty($party);
        });
    }

    public function update(Party $party, array $data, ?int $userId = null): Party
    {
        return DB::transaction(function () use ($party, $data, $userId): Party {
            [$partyData, $mappings] = $this->prepareData($data, $party);
            $partyData['updated_by'] = $userId;

            $party->update($partyData);
            $this->syncLedgerMappings($party, $mappings, $userId);

            return $this->freshParty($party);
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array{purpose:string, chart_of_account_id:int}>}
     */
    private function prepareData(array $data, ?Party $party = null): array
    {
        $mappings = collect($data['ledger_mappings'] ?? [])
            ->filter(fn ($mapping) => is_array($mapping)
                && ! empty($mapping['purpose'])
                && ! empty($mapping['chart_of_account_id']))
            ->map(fn (array $mapping) => [
                'purpose' => (string) $mapping['purpose'],
                'chart_of_account_id' => (int) $mapping['chart_of_account_id'],
            ])
            ->unique('purpose')
            ->values()
            ->all();

        $partyData = Arr::except($data, [
            'company_id',
            'ledger_mappings',
            'receivable_ledger_account_id',
            'payable_ledger_account_id',
            'capital_ledger_account_id',
            'payable_capital_ledger_account_id',
        ]);

        $partyData['party_name'] = trim((string) ($partyData['party_name'] ?? $party?->party_name));
        $partyData['sub_type'] = $this->normalizeSubType($partyData['sub_type'] ?? null);
        $partyData['opening_balance'] = $this->amount($partyData['opening_balance'] ?? 0);
        $partyData['credit_limit'] = $this->nullableAmount($partyData['credit_limit'] ?? null);
        $partyData['salary_amount'] = $this->nullableAmount($partyData['salary_amount'] ?? null);
        $partyData['ownership_percentage'] = $this->nullableAmount($partyData['ownership_percentage'] ?? null);
        $partyData['status'] = $partyData['status'] ?? 'Active';

        $partyTypeId = (int) ($partyData['party_type_id'] ?? $party?->party_type_id ?? 0);
        $partyType = $partyTypeId > 0 ? PartyType::query()->find($partyTypeId) : null;
        $partyData['default_ledger_nature'] = PartyAccountingProfile::deriveNature(
            $partyType,
            collect($mappings)->pluck('purpose')->all(),
            $partyData['default_ledger_nature'] ?? $party?->default_ledger_nature
        );

        $primaryPurpose = PartyAccountingProfile::purposeFromNature($partyData['default_ledger_nature']);
        $primaryMapping = collect($mappings)->firstWhere('purpose', $primaryPurpose)
            ?: collect($mappings)->first();
        $partyData['linked_ledger_account_id'] = $primaryMapping['chart_of_account_id']
            ?? $partyData['linked_ledger_account_id']
            ?? $party?->linked_ledger_account_id;

        return [$partyData, $mappings];
    }

    /** @param array<int, array{purpose:string, chart_of_account_id:int}> $mappings */
    private function syncLedgerMappings(Party $party, array $mappings, ?int $userId): void
    {
        $purposes = collect($mappings)->pluck('purpose')->all();

        $party->ledgerMappings()
            ->when($purposes !== [], fn ($query) => $query->whereNotIn('mapping_purpose', $purposes))
            ->when($purposes === [], fn ($query) => $query)
            ->delete();

        foreach ($mappings as $mapping) {
            $ledger = ChartOfAccount::query()->findOrFail($mapping['chart_of_account_id']);
            $existing = $party->ledgerMappings()
                ->where('mapping_purpose', $mapping['purpose'])
                ->first();

            $party->ledgerMappings()->updateOrCreate(
                ['mapping_purpose' => $mapping['purpose']],
                [
                    'company_id' => $party->company_id,
                    'chart_of_account_id' => $ledger->id,
                    'status' => $party->status === 'Active' ? 'Active' : 'Inactive',
                    'created_by' => $existing?->created_by ?: $userId,
                    'updated_by' => $userId,
                ]
            );
        }
    }

    private function nextPartyCode(): string
    {
        $lastCode = Party::query()
            ->withTrashed()
            ->whereNotNull('party_code')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('party_code');

        if (! $lastCode) {
            return 'P-00001';
        }

        preg_match('/(\d+)$/', $lastCode, $matches);
        $number = (int) ($matches[1] ?? 0);

        return 'P-' . str_pad((string) ($number + 1), 5, '0', STR_PAD_LEFT);
    }

    private function freshParty(Party $party): Party
    {
        return $party->fresh([
            'partyType.defaultLedger.accountType',
            'linkedLedger.accountType',
            'ledgerMappings.ledger.accountType',
            'receivableLedgerMapping.ledger.accountType',
            'payableLedgerMapping.ledger.accountType',
            'capitalLedgerMapping.ledger.accountType',
        ]);
    }


    private function normalizeSubType(mixed $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }

    private function amount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.00;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }

    private function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }
}
