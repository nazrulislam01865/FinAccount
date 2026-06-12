<?php

namespace App\Support;

use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\PartyLedgerMapping;
use App\Models\PartyType;

final class PartyAccountingProfile
{
    public const NATURE_RECEIVABLE = 'Receivable';
    public const NATURE_PAYABLE = 'Payable';
    public const NATURE_ADVANCE_PAID = 'Advance Paid';
    public const NATURE_ADVANCE_RECEIVED = 'Advance Received';
    public const NATURE_CAPITAL = 'Capital';
    public const NATURE_NO_EFFECT = 'No Effect';

    public const NATURES = [
        self::NATURE_RECEIVABLE,
        self::NATURE_PAYABLE,
        self::NATURE_ADVANCE_PAID,
        self::NATURE_ADVANCE_RECEIVED,
        self::NATURE_CAPITAL,
        self::NATURE_NO_EFFECT,
    ];

    /**
     * Ledger Nature is a setup default, not a transaction posting instruction.
     * Explicit party ledger mappings and accounting rules remain the source of truth.
     */
    public static function deriveNature(
        ?PartyType $partyType,
        array $mappingPurposes = [],
        ?string $legacyNature = null
    ): string {
        $configured = self::normalizeNature($partyType?->default_ledger_nature);

        if ($configured !== self::NATURE_NO_EFFECT) {
            return $configured;
        }

        $inferred = self::inferFromPartyType($partyType);

        if ($inferred !== self::NATURE_NO_EFFECT) {
            return $inferred;
        }

        $legacy = self::normalizeNature($legacyNature);

        if ($legacy !== self::NATURE_NO_EFFECT) {
            return $legacy;
        }

        $mappedNatures = collect($mappingPurposes)
            ->map(fn ($purpose) => self::natureFromPurpose((string) $purpose))
            ->reject(fn ($nature) => $nature === self::NATURE_NO_EFFECT)
            ->unique()
            ->values();

        return $mappedNatures->count() === 1
            ? (string) $mappedNatures->first()
            : self::NATURE_NO_EFFECT;
    }

    public static function effectiveNature(Party $party): string
    {
        $party->loadMissing('partyType', 'ledgerMappings');

        return self::deriveNature(
            $party->partyType,
            $party->ledgerMappings
                ->where('status', 'Active')
                ->pluck('mapping_purpose')
                ->all(),
            $party->default_ledger_nature
        );
    }

    public static function inferFromPartyType(?PartyType $partyType): string
    {
        $value = strtoupper(trim((string) $partyType?->code . ' ' . (string) $partyType?->name));

        return match (true) {
            str_contains($value, 'CUSTOMER'),
            str_contains($value, 'CUS'),
            str_contains($value, 'TENANT') => self::NATURE_RECEIVABLE,

            str_contains($value, 'SUPPLIER'),
            str_contains($value, 'SUP'),
            str_contains($value, 'VENDOR'),
            str_contains($value, 'LANDLORD'),
            str_contains($value, 'EMPLOYEE'),
            str_contains($value, 'DRIVER'),
            str_contains($value, 'LENDER') => self::NATURE_PAYABLE,

            str_contains($value, 'OWNER'),
            str_contains($value, 'PARTNER'),
            str_contains($value, 'SHAREHOLDER') => self::NATURE_CAPITAL,

            default => self::NATURE_NO_EFFECT,
        };
    }

    public static function normalizeNature(?string $nature): string
    {
        $nature = trim((string) $nature);

        return in_array($nature, self::NATURES, true)
            ? $nature
            : self::NATURE_NO_EFFECT;
    }

    public static function purposeFromNature(?string $nature): string
    {
        return match (self::normalizeNature($nature)) {
            self::NATURE_RECEIVABLE => PartyLedgerMapping::PURPOSE_RECEIVABLE,
            self::NATURE_PAYABLE => PartyLedgerMapping::PURPOSE_PAYABLE,
            self::NATURE_ADVANCE_PAID => PartyLedgerMapping::PURPOSE_ADVANCE_PAID,
            self::NATURE_ADVANCE_RECEIVED => PartyLedgerMapping::PURPOSE_ADVANCE_RECEIVED,
            self::NATURE_CAPITAL => PartyLedgerMapping::PURPOSE_CAPITAL,
            default => PartyLedgerMapping::PURPOSE_GENERAL,
        };
    }

    public static function natureFromPurpose(?string $purpose): string
    {
        return match ($purpose) {
            PartyLedgerMapping::PURPOSE_RECEIVABLE => self::NATURE_RECEIVABLE,
            PartyLedgerMapping::PURPOSE_PAYABLE,
            PartyLedgerMapping::PURPOSE_LOAN_PAYABLE,
            PartyLedgerMapping::PURPOSE_SALARY_PAYABLE => self::NATURE_PAYABLE,
            PartyLedgerMapping::PURPOSE_ADVANCE_PAID => self::NATURE_ADVANCE_PAID,
            PartyLedgerMapping::PURPOSE_ADVANCE_RECEIVED => self::NATURE_ADVANCE_RECEIVED,
            PartyLedgerMapping::PURPOSE_CAPITAL => self::NATURE_CAPITAL,
            default => self::NATURE_NO_EFFECT,
        };
    }

    public static function purposeForAccount(?ChartOfAccount $account): string
    {
        $accountType = $account?->accountType?->name;
        $normalBalance = $account?->normal_balance ?: $account?->accountType?->normal_balance;

        return match (true) {
            $accountType === 'Asset' && $normalBalance === 'Debit' => PartyLedgerMapping::PURPOSE_RECEIVABLE,
            $accountType === 'Liability' && $normalBalance === 'Credit' => PartyLedgerMapping::PURPOSE_PAYABLE,
            $accountType === 'Equity' && $normalBalance === 'Credit' => PartyLedgerMapping::PURPOSE_CAPITAL,
            default => PartyLedgerMapping::PURPOSE_GENERAL,
        };
    }

    public static function openingSideForPurpose(?string $purpose): ?string
    {
        return match ($purpose) {
            PartyLedgerMapping::PURPOSE_RECEIVABLE,
            PartyLedgerMapping::PURPOSE_ADVANCE_PAID => 'Debit',

            PartyLedgerMapping::PURPOSE_PAYABLE,
            PartyLedgerMapping::PURPOSE_ADVANCE_RECEIVED,
            PartyLedgerMapping::PURPOSE_LOAN_PAYABLE,
            PartyLedgerMapping::PURPOSE_SALARY_PAYABLE,
            PartyLedgerMapping::PURPOSE_CAPITAL => 'Credit',

            default => null,
        };
    }
}
