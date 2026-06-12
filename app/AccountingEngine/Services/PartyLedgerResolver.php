<?php

namespace App\AccountingEngine\Services;

use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\PartyLedgerMapping;
use App\Support\PartyAccountingProfile;
use Illuminate\Validation\ValidationException;

class PartyLedgerResolver
{
    public function resolve(Party $party, string $purpose, bool $required = true): ?ChartOfAccount
    {
        $purpose = $this->normalizePurpose($purpose);
        $party->loadMissing('ledgerMappings.ledger.accountType', 'linkedLedger.accountType');

        $mapping = $party->ledgerMappings
            ->first(fn (PartyLedgerMapping $item) => $item->status === 'Active'
                && $item->mapping_purpose === $purpose
                && $item->ledger);

        if ($mapping?->ledger) {
            return $mapping->ledger;
        }

        $fallback = $this->legacyFallback($party, $purpose);
        if ($fallback) {
            return $fallback;
        }

        if (! $required) {
            return null;
        }

        throw ValidationException::withMessages([
            'party_id' => sprintf(
                '%s does not have an active %s ledger mapping. Update Party Setup before posting this transaction.',
                $party->party_name,
                str_replace('_', ' ', $purpose)
            ),
        ]);
    }

    public function purposeForLegacyPartyControl(Party $party, ?string $allowedLedgerType = null): string
    {
        $allowed = strtolower(trim(str_replace(['_', '-'], ' ', (string) $allowedLedgerType)));
        $allowed = preg_replace('/\s+/', ' ', $allowed) ?: '';

        return match (true) {
            str_contains($allowed, 'advance paid') => PartyLedgerMapping::PURPOSE_ADVANCE_PAID,
            str_contains($allowed, 'advance received') => PartyLedgerMapping::PURPOSE_ADVANCE_RECEIVED,
            str_contains($allowed, 'salary') => PartyLedgerMapping::PURPOSE_SALARY_PAYABLE,
            str_contains($allowed, 'loan') => PartyLedgerMapping::PURPOSE_LOAN_PAYABLE,
            str_contains($allowed, 'capital'), str_contains($allowed, 'equity'), str_contains($allowed, 'owner') => PartyLedgerMapping::PURPOSE_CAPITAL,
            str_contains($allowed, 'receivable'), str_contains($allowed, 'customer') => PartyLedgerMapping::PURPOSE_RECEIVABLE,
            str_contains($allowed, 'payable'), str_contains($allowed, 'supplier') => PartyLedgerMapping::PURPOSE_PAYABLE,
            default => PartyAccountingProfile::purposeFromNature($party->effectiveLedgerNature()),
        };
    }


    public function purposeFromNature(?string $nature): string
    {
        return PartyAccountingProfile::purposeFromNature($nature);
    }

    private function legacyFallback(Party $party, string $purpose): ?ChartOfAccount
    {
        $legacyPurpose = PartyAccountingProfile::purposeFromNature($party->effectiveLedgerNature());

        if ($purpose === PartyLedgerMapping::PURPOSE_GENERAL) {
            $singleMapping = $party->ledgerMappings
                ->filter(fn (PartyLedgerMapping $mapping) => $mapping->status === 'Active' && $mapping->ledger)
                ->values();

            if ($singleMapping->count() === 1) {
                return $singleMapping->first()->ledger;
            }
        }

        if ($party->linkedLedger && in_array($purpose, [$legacyPurpose, PartyLedgerMapping::PURPOSE_GENERAL], true)) {
            return $party->linkedLedger;
        }

        if ($purpose === PartyLedgerMapping::PURPOSE_SALARY_PAYABLE) {
            return $this->resolve($party, PartyLedgerMapping::PURPOSE_PAYABLE, false);
        }

        if ($purpose === PartyLedgerMapping::PURPOSE_LOAN_PAYABLE) {
            return $this->resolve($party, PartyLedgerMapping::PURPOSE_PAYABLE, false);
        }

        return null;
    }

    private function normalizePurpose(string $purpose): string
    {
        $purpose = strtolower(trim(str_replace([' ', '-'], '_', $purpose)));

        return in_array($purpose, PartyLedgerMapping::PURPOSES, true)
            ? $purpose
            : PartyLedgerMapping::PURPOSE_GENERAL;
    }
}
