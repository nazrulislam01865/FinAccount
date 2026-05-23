<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\LedgerMappingRule;
use App\Models\TransactionHead;

class TransactionRequirementService
{
    /**
     * Resolve the dynamic SRS-driven input requirements for Transaction Entry.
     *
     * The SRS says transaction fields must be shown/required from the selected
     * Transaction Head and Accounting Rule. Therefore party/cash-bank should not
     * be globally required. It becomes required only when the active V2 rule or
     * legacy mapping says so, or when a party-control/cash-bank ledger source is used.
     *
     * @return array<string, mixed>
     */
    public function resolve(
        int $transactionHeadId,
        ?int $settlementTypeId = null,
        ?int $companyId = null
    ): array {
        $head = TransactionHead::query()
            ->with(['defaultPartyType', 'defaultPrimaryLedger.accountType'])
            ->find($transactionHeadId);

        $requirements = $this->headDefaults($head);

        if (! $head) {
            return $requirements;
        }

        $companyId = $this->companyId($companyId);

        $rule = $this->activeAccountingRule($head->id, $settlementTypeId, $companyId);
        if ($rule) {
            return $this->mergeRuleRequirements($requirements, $rule);
        }

        $legacy = $this->activeLegacyRule($head->id, $settlementTypeId, $companyId);
        if ($legacy) {
            return $this->mergeLegacyRequirements($requirements, $legacy);
        }

        return $requirements;
    }

    public function isPartyRequired(array $requirements): bool
    {
        return $this->normalizePartyMode($requirements['party_required_mode'] ?? 'No') === 'Required';
    }

    public function isCashBankRequired(array $requirements): bool
    {
        return (bool) ($requirements['cash_bank_required'] ?? false);
    }

    public function normalizePartyMode(?string $mode): string
    {
        $value = strtolower(trim((string) $mode));

        return match ($value) {
            'yes', 'required', 'require', 'always', 'party required', 'customer_required', 'supplier_required' => 'Required',
            'optional', 'allowed', 'if available' => 'Optional',
            default => 'No',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function headDefaults(?TransactionHead $head): array
    {
        $partyMode = $this->normalizePartyMode($head?->party_required_mode ?: ($head?->requires_party ? 'Required' : 'No'));

        return [
            'source' => 'transaction_head',
            'transaction_head_id' => $head?->id,
            'transaction_screen' => $head?->transaction_screen ?: 'Transaction Entry',
            'category' => $head?->category ?: $head?->nature,
            'party_required_mode' => $partyMode,
            'party_required' => $partyMode === 'Required',
            'party_optional' => $partyMode === 'Optional',
            'party_type_id' => $head?->default_party_type_id,
            'party_type_name' => $head?->defaultPartyType?->name,
            'payment_method_required' => (bool) ($head?->payment_method_required ?? false),
            'cash_bank_required' => (bool) ($head?->payment_method_required ?? false),
            'requires_reference' => (bool) ($head?->requires_reference ?? false),
            'help_text' => $head?->help_text,
        ];
    }

    private function activeAccountingRule(int $transactionHeadId, ?int $settlementTypeId, int $companyId): ?AccountingRule
    {
        return AccountingRule::query()
            ->with(['lines.ledger.accountType', 'partyType'])
            ->where('company_id', $companyId)
            ->where('transaction_head_id', $transactionHeadId)
            ->where('status', 'Active')
            ->where(function ($query) use ($settlementTypeId) {
                if ($settlementTypeId) {
                    $query->where('settlement_type_id', $settlementTypeId)
                        ->orWhereNull('settlement_type_id');
                } else {
                    $query->whereNull('settlement_type_id');
                }
            })
            ->orderByRaw('CASE WHEN settlement_type_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    private function activeLegacyRule(int $transactionHeadId, ?int $settlementTypeId, int $companyId): ?LedgerMappingRule
    {
        return LedgerMappingRule::query()
            ->with(['debitAccount.accountType', 'creditAccount.accountType'])
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where('transaction_head_id', $transactionHeadId)
            ->when($settlementTypeId, fn ($query) => $query->where('settlement_type_id', $settlementTypeId))
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * @param array<string, mixed> $requirements
     * @return array<string, mixed>
     */
    private function mergeRuleRequirements(array $requirements, AccountingRule $rule): array
    {
        $lineRequiresParty = $rule->lines->contains(function (AccountingRuleLine $line): bool {
            return $this->ledgerSource($line->ledger_source) === 'party_control'
                || (bool) $line->ledger?->is_party_control;
        });

        $lineRequiresCashBank = $rule->lines->contains(function (AccountingRuleLine $line): bool {
            return $this->ledgerSource($line->ledger_source) === 'user_cash_bank'
                || (bool) $line->ledger?->is_cash_bank;
        });

        $partyMode = $this->normalizePartyMode($rule->party_required_mode);
        if ($partyMode === 'No' && $lineRequiresParty) {
            $partyMode = 'Required';
        }

        $cashBankRequired = (bool) $rule->cash_bank_ledger_required
            || (bool) $rule->payment_method_required
            || $lineRequiresCashBank;

        return array_merge($requirements, [
            'source' => 'accounting_rule',
            'accounting_rule_id' => $rule->id,
            'accounting_rule_code' => $rule->rule_code,
            'party_required_mode' => $partyMode,
            'party_required' => $partyMode === 'Required',
            'party_optional' => $partyMode === 'Optional',
            'party_type_id' => $rule->party_type_id ?: $requirements['party_type_id'],
            'party_type_name' => $rule->partyType?->name ?: $requirements['party_type_name'],
            'payment_method_required' => (bool) $rule->payment_method_required || $cashBankRequired,
            'cash_bank_required' => $cashBankRequired,
            'transaction_screen' => $rule->transaction_screen ?: $requirements['transaction_screen'],
        ]);
    }

    /**
     * @param array<string, mixed> $requirements
     * @return array<string, mixed>
     */
    private function mergeLegacyRequirements(array $requirements, LedgerMappingRule $rule): array
    {
        $partyMode = $this->normalizePartyMode($rule->party_required_mode);
        if ($partyMode === 'No' && $this->legacyRequiresParty($rule)) {
            $partyMode = 'Required';
        }

        $cashBankRequired = (bool) $rule->cash_bank_ledger_required
            || (bool) $rule->payment_method_required
            || (bool) $rule->debitAccount?->is_cash_bank
            || (bool) $rule->creditAccount?->is_cash_bank
            || $this->settlementLooksLikeCashBank($rule->settlementType?->code, $rule->settlementType?->name);

        return array_merge($requirements, [
            'source' => 'legacy_mapping_rule',
            'legacy_ledger_mapping_rule_id' => $rule->id,
            'party_required_mode' => $partyMode,
            'party_required' => $partyMode === 'Required',
            'party_optional' => $partyMode === 'Optional',
            'payment_method_required' => (bool) $rule->payment_method_required || $cashBankRequired,
            'cash_bank_required' => $cashBankRequired,
            'transaction_screen' => $rule->transaction_screen ?: $requirements['transaction_screen'],
        ]);
    }

    private function legacyRequiresParty(LedgerMappingRule $rule): bool
    {
        $partyEffect = strtoupper(trim((string) $rule->party_ledger_effect));

        return ($partyEffect !== '' && $partyEffect !== 'NO EFFECT')
            || (bool) $rule->debitAccount?->is_party_control
            || (bool) $rule->creditAccount?->is_party_control;
    }

    private function ledgerSource(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        return match (true) {
            $source === 'user_cash_bank', str_contains($source, 'cash'), str_contains($source, 'bank'), str_contains($source, 'payment method') => 'user_cash_bank',
            $source === 'party_control', str_contains($source, 'party') => 'party_control',
            $source === 'transaction_head', str_contains($source, 'transaction head') => 'transaction_head',
            $source === 'system_derived', str_contains($source, 'system') => 'system_derived',
            default => 'fixed',
        };
    }

    private function settlementLooksLikeCashBank(?string $code, ?string $name): bool
    {
        $value = strtoupper(trim((string) $code . ' ' . (string) $name));

        return str_contains($value, 'CASH')
            || str_contains($value, 'BANK')
            || str_contains($value, 'ADVANCE_PAID')
            || str_contains($value, 'ADVANCE PAID')
            || str_contains($value, 'ADVANCE_RECEIVED')
            || str_contains($value, 'ADVANCE RECEIVED');
    }

    private function companyId(?int $companyId): int
    {
        if ($companyId && $companyId > 0) {
            return $companyId;
        }

        return (int) Company::query()->orderBy('id')->value('id');
    }
}
