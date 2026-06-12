<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('party_types') || ! Schema::hasTable('parties')) {
            return;
        }

        $this->normalizePartyTypeNatures();
        $this->normalizePartiesAndMappings();
    }

    public function down(): void
    {
        // Data-normalization migration: intentionally non-destructive.
    }

    private function normalizePartyTypeNatures(): void
    {
        DB::table('party_types')
            ->orderBy('id')
            ->chunkById(200, function ($types): void {
                foreach ($types as $type) {
                    $current = $this->normalizeNature($type->default_ledger_nature ?? null);
                    $inferred = $this->inferNature($type->code ?? null, $type->name ?? null);
                    $nature = $current === 'No Effect' && $inferred !== 'No Effect'
                        ? $inferred
                        : $current;

                    if (($type->default_ledger_nature ?? null) !== $nature) {
                        DB::table('party_types')->where('id', $type->id)->update([
                            'default_ledger_nature' => $nature,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }, 'id');
    }

    private function normalizePartiesAndMappings(): void
    {
        $typeNatures = DB::table('party_types')
            ->pluck('default_ledger_nature', 'id');

        DB::table('parties')
            ->orderBy('id')
            ->chunkById(200, function ($parties) use ($typeNatures): void {
                foreach ($parties as $party) {
                    $subType = $this->normalizeSubType($party->sub_type ?? null);
                    $typeNature = $this->normalizeNature(
                        $typeNatures[(int) ($party->party_type_id ?? 0)] ?? null
                    );
                    $legacyNature = $this->normalizeNature($party->default_ledger_nature ?? null);
                    $nature = $typeNature !== 'No Effect' ? $typeNature : $legacyNature;

                    if ($nature === 'No Effect' && Schema::hasTable('party_ledger_mappings')) {
                        $purposes = DB::table('party_ledger_mappings')
                            ->where('party_id', $party->id)
                            ->where('status', 'Active')
                            ->pluck('mapping_purpose')
                            ->map(fn ($purpose) => $this->natureFromPurpose((string) $purpose))
                            ->reject(fn ($mappedNature) => $mappedNature === 'No Effect')
                            ->unique()
                            ->values();

                        if ($purposes->count() === 1) {
                            $nature = (string) $purposes->first();
                        }
                    }

                    $updates = [];

                    if (($party->sub_type ?? null) !== $subType) {
                        $updates['sub_type'] = $subType;
                    }

                    if (($party->default_ledger_nature ?? null) !== $nature) {
                        $updates['default_ledger_nature'] = $nature;
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('parties')->where('id', $party->id)->update($updates);
                    }

                    if (! Schema::hasTable('party_ledger_mappings') || ! $party->linked_ledger_account_id) {
                        continue;
                    }

                    $purpose = $this->purposeFromNature($nature);

                    if ($purpose === 'general') {
                        $purpose = $this->purposeFromLedger((int) $party->linked_ledger_account_id);
                    }

                    $existing = DB::table('party_ledger_mappings')
                        ->where('party_id', $party->id)
                        ->where('mapping_purpose', $purpose)
                        ->first();

                    if ($existing && ! $existing->chart_of_account_id) {
                        DB::table('party_ledger_mappings')->where('id', $existing->id)->update([
                            'chart_of_account_id' => $party->linked_ledger_account_id,
                            'status' => $party->status === 'Inactive' ? 'Inactive' : 'Active',
                            'updated_by' => $party->updated_by,
                            'updated_at' => now(),
                        ]);
                    }

                    if (! $existing) {
                        $general = DB::table('party_ledger_mappings')
                            ->where('party_id', $party->id)
                            ->where('mapping_purpose', 'general')
                            ->where('chart_of_account_id', $party->linked_ledger_account_id)
                            ->first();

                        if ($general && $purpose !== 'general') {
                            DB::table('party_ledger_mappings')->where('id', $general->id)->update([
                                'mapping_purpose' => $purpose,
                                'updated_at' => now(),
                            ]);
                        } else {
                            DB::table('party_ledger_mappings')->insert([
                                'company_id' => $party->company_id,
                                'party_id' => $party->id,
                                'mapping_purpose' => $purpose,
                                'chart_of_account_id' => $party->linked_ledger_account_id,
                                'status' => $party->status === 'Inactive' ? 'Inactive' : 'Active',
                                'created_by' => $party->created_by,
                                'updated_by' => $party->updated_by,
                                'created_at' => $party->created_at ?? now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }, 'id');
    }

    private function normalizeSubType(?string $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function normalizeNature(?string $nature): string
    {
        return in_array($nature, [
            'Receivable',
            'Payable',
            'Advance Paid',
            'Advance Received',
            'Capital',
            'No Effect',
        ], true) ? $nature : 'No Effect';
    }

    private function inferNature(?string $code, ?string $name): string
    {
        $value = strtoupper(trim((string) $code . ' ' . (string) $name));

        return match (true) {
            str_contains($value, 'CUSTOMER'),
            str_contains($value, 'CUS'),
            str_contains($value, 'TENANT') => 'Receivable',

            str_contains($value, 'SUPPLIER'),
            str_contains($value, 'SUP'),
            str_contains($value, 'VENDOR'),
            str_contains($value, 'LANDLORD'),
            str_contains($value, 'EMPLOYEE'),
            str_contains($value, 'DRIVER'),
            str_contains($value, 'LENDER') => 'Payable',

            str_contains($value, 'OWNER'),
            str_contains($value, 'PARTNER'),
            str_contains($value, 'SHAREHOLDER') => 'Capital',

            default => 'No Effect',
        };
    }

    private function purposeFromNature(string $nature): string
    {
        return match ($nature) {
            'Receivable' => 'receivable',
            'Payable' => 'payable',
            'Advance Paid' => 'advance_paid',
            'Advance Received' => 'advance_received',
            'Capital' => 'capital',
            default => 'general',
        };
    }

    private function natureFromPurpose(string $purpose): string
    {
        return match ($purpose) {
            'receivable' => 'Receivable',
            'payable', 'loan_payable', 'salary_payable' => 'Payable',
            'advance_paid' => 'Advance Paid',
            'advance_received' => 'Advance Received',
            'capital' => 'Capital',
            default => 'No Effect',
        };
    }

    private function purposeFromLedger(int $ledgerId): string
    {
        $ledger = DB::table('chart_of_accounts as coa')
            ->leftJoin('account_types as at', 'at.id', '=', 'coa.account_type_id')
            ->where('coa.id', $ledgerId)
            ->first([
                'coa.normal_balance',
                'at.name as account_type',
                'at.normal_balance as type_normal_balance',
            ]);

        $normalBalance = $ledger?->normal_balance ?: $ledger?->type_normal_balance;

        return match (true) {
            $ledger?->account_type === 'Asset' && $normalBalance === 'Debit' => 'receivable',
            $ledger?->account_type === 'Liability' && $normalBalance === 'Credit' => 'payable',
            $ledger?->account_type === 'Equity' && $normalBalance === 'Credit' => 'capital',
            default => 'general',
        };
    }
};
