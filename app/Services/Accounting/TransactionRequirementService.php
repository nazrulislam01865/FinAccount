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
        $category = TransactionHead::normaliseCategory($head?->category, $head?->name, $head?->nature);

        // Transaction Head contains only the business activity and Posting COA.
        // Party, money-account and other input requirements are intentionally
        // neutral here and are supplied by the selected Accounting Rule.
        return [
            'source' => 'transaction_head',
            'transaction_head_id' => $head?->id,
            'transaction_screen' => $this->screenFromCategory($category),
            'category' => $category,
            'party_required_mode' => 'No',
            'party_required' => false,
            'party_optional' => false,
            'party_type_id' => null,
            'party_type_name' => null,
            'payment_method_required' => false,
            'cash_bank_required' => false,
            'requires_reference' => false,
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
            ->with(['debitAccount.accountType', 'creditAccount.accountType', 'transactionHead.defaultPartyType'])
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
            return str_starts_with($this->ledgerSource($line->ledger_source), 'party_')
                || $this->ledgerSource($line->ledger_source) === 'party_control'
                || (bool) $line->ledger?->is_party_control;
        });

        $lineRequiresCashBank = $rule->lines->contains(function (AccountingRuleLine $line): bool {
            return $this->ledgerSource($line->ledger_source) === 'user_cash_bank'
                || (bool) $line->ledger?->is_cash_bank;
        });

        $requiredMappingPurposes = $rule->lines
            ->map(fn (AccountingRuleLine $line) => $this->partyMappingPurpose($this->ledgerSource($line->ledger_source)))
            ->filter()
            ->unique()
            ->values()
            ->all();

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
            'required_party_mapping_purposes' => $requiredMappingPurposes,
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

        $legacyPartyType = $rule->transactionHead?->defaultPartyType;

        return array_merge($requirements, [
            'source' => 'legacy_mapping_rule',
            'legacy_ledger_mapping_rule_id' => $rule->id,
            'party_required_mode' => $partyMode,
            'party_required' => $partyMode === 'Required',
            'party_optional' => $partyMode === 'Optional',
            'party_type_id' => $legacyPartyType?->id,
            'party_type_name' => $legacyPartyType?->name,
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
            $source === 'party_receivable', str_contains($source, 'party_receivable'), str_contains($source, 'customer_receivable') => 'party_receivable',
            $source === 'party_payable', str_contains($source, 'party_payable'), str_contains($source, 'supplier_payable') => 'party_payable',
            str_contains($source, 'party_advance_paid') => 'party_advance_paid',
            str_contains($source, 'party_advance_received') => 'party_advance_received',
            str_contains($source, 'party_loan_payable') => 'party_loan_payable',
            str_contains($source, 'party_salary_payable') => 'party_salary_payable',
            str_contains($source, 'party_capital') => 'party_capital',
            $source === 'party_control', str_contains($source, 'party') => 'party_control',
            $source === 'transaction_head', str_contains($source, 'transaction head') => 'transaction_head',
            $source === 'system_derived', str_contains($source, 'system') => 'system_derived',
            default => 'fixed',
        };
    }


    private function partyMappingPurpose(string $source): ?string
    {
        return match ($source) {
            'party_receivable' => 'receivable',
            'party_payable' => 'payable',
            'party_advance_paid' => 'advance_paid',
            'party_advance_received' => 'advance_received',
            'party_loan_payable' => 'loan_payable',
            'party_salary_payable' => 'salary_payable',
            'party_capital' => 'capital',
            default => null,
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

    private function screenFromCategory(?string $category): string
    {
        return match (TransactionHead::normaliseCategory($category)) {
            'Opening' => 'Opening Balance Entry',
            'Owner / Equity' => 'Owner / Equity Entry',
            default => TransactionHead::normaliseCategory($category) . ' Entry',
        };
    }

    private function companyId(?int $companyId): int
    {
        if ($companyId && $companyId > 0) {
            return $companyId;
        }

        return (int) Company::query()->orderBy('id')->value('id');
    }
}
