<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Support\Collection;

class TransactionHeadConfigurationService
{
    /**
     * Build the effective Transaction Head profile from Accounting Rules.
     *
     * Transaction Head stores only the business activity and posting COA.
     * Party, money-account, settlement and journal behavior are derived from
     * active Accounting Rules (with legacy mappings used only as a fallback).
     *
     * @return array<string, mixed>
     */
    public function summarize(TransactionHead $head): array
    {
        $relations = [];

        if (! $head->relationLoaded('defaultPrimaryLedger')) {
            $relations[] = 'defaultPrimaryLedger.accountType';
        }
        if (! $head->relationLoaded('defaultPartyType')) {
            $relations[] = 'defaultPartyType';
        }
        if (! $head->relationLoaded('accountingRules')) {
            $relations[] = 'accountingRules.lines';
            $relations[] = 'accountingRules.settlementType';
            $relations[] = 'accountingRules.partyType';
        }
        if (! $head->relationLoaded('ledgerMappingRules')) {
            $relations[] = 'ledgerMappingRules.settlementType';
        }
        if (! $head->relationLoaded('settlementTypes')) {
            $relations[] = 'settlementTypes';
        }

        if ($relations !== []) {
            $head->load($relations);
        }

        $activeRules = $head->accountingRules
            ->where('status', 'Active')
            ->filter(fn (AccountingRule $rule): bool => $this->isRuleReady($rule, $head))
            ->values();

        $activeLegacyRules = $head->ledgerMappingRules
            ->where('status', 'Active')
            ->filter(fn (LedgerMappingRule $rule): bool => $this->isLegacyRuleReady($rule))
            ->values();

        $hasRule = $activeRules->isNotEmpty() || $activeLegacyRules->isNotEmpty();
        $ready = $head->status === 'Active'
            && (bool) ($head->is_user_selectable ?? true)
            && $hasRule;

        $settlements = $this->settlements($head, $activeRules, $activeLegacyRules);
        $partyMode = $this->partyMode($activeRules, $activeLegacyRules);
        $partyType = $activeRules->pluck('partyType')->filter()->unique('id')->values();
        if ($partyType->isEmpty() && $activeLegacyRules->isNotEmpty() && $head->defaultPartyType) {
            $partyType = collect([$head->defaultPartyType]);
        }
        $cashBankRequired = $this->cashBankRequired($activeRules, $activeLegacyRules);

        return [
            'ready' => $ready,
            'setup_status' => $head->status !== 'Active'
                ? 'Inactive'
                : ($hasRule ? 'Ready' : 'Accounting Rule Required'),
            'active_rule_count' => $activeRules->count() + $activeLegacyRules->count(),
            'modern_rule_count' => $activeRules->count(),
            'legacy_rule_count' => $activeLegacyRules->count(),
            'party_required_mode' => $partyMode,
            'party_required' => $partyMode === 'Required',
            'party_optional' => $partyMode === 'Optional',
            'party_type_id' => $partyType->count() === 1 ? $partyType->first()?->id : null,
            'party_type_name' => $partyType->count() === 1 ? $partyType->first()?->name : null,
            'cash_bank_required' => $cashBankRequired,
            'payment_method_required' => $cashBankRequired,
            'requires_reference' => false,
            'transaction_screen' => $this->transactionScreen($head, $activeRules, $activeLegacyRules),
            'settlements' => $settlements,
            'settlement_type_ids' => $settlements->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'settlement_names' => $settlements->pluck('name')->values(),
            'uses_generic_rule' => $activeRules->contains(fn (AccountingRule $rule): bool => $rule->settlement_type_id === null),
        ];
    }

    public function isReady(TransactionHead $head): bool
    {
        return (bool) $this->summarize($head)['ready'];
    }

    private function isRuleReady(AccountingRule $rule, TransactionHead $head): bool
    {
        $lines = $rule->lines;

        if ($lines->count() < 2) {
            return false;
        }

        $hasDebit = $lines->contains(fn (AccountingRuleLine $line): bool => $line->side === 'Debit');
        $hasCredit = $lines->contains(fn (AccountingRuleLine $line): bool => $line->side === 'Credit');

        if (! $hasDebit || ! $hasCredit) {
            return false;
        }

        $needsHeadLedger = $lines->contains(function (AccountingRuleLine $line): bool {
            $source = strtolower(trim((string) $line->ledger_source));

            return $source === 'transaction_head' || str_contains($source, 'transaction head');
        });

        if ($needsHeadLedger && ! $head->default_primary_ledger_id) {
            return false;
        }

        return ! $lines->contains(function (AccountingRuleLine $line): bool {
            $source = strtolower(trim((string) $line->ledger_source));

            return in_array($source, ['', 'fixed'], true) && ! $line->ledger_id;
        });
    }

    private function isLegacyRuleReady(LedgerMappingRule $rule): bool
    {
        if ($rule->debit_account_id && $rule->credit_account_id) {
            return true;
        }

        $primarySource = trim((string) $rule->primary_ledger_source);
        $counterSource = trim((string) $rule->counter_ledger_source);

        return $primarySource !== '' && $counterSource !== '';
    }

    /**
     * @param Collection<int, AccountingRule> $activeRules
     * @param Collection<int, LedgerMappingRule> $activeLegacyRules
     * @return Collection<int, SettlementType>
     */
    private function settlements(
        TransactionHead $head,
        Collection $activeRules,
        Collection $activeLegacyRules
    ): Collection {
        if ($activeRules->contains(fn (AccountingRule $rule): bool => $rule->settlement_type_id === null)) {
            return SettlementType::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        $ruleSettlements = $activeRules
            ->pluck('settlementType')
            ->merge($activeLegacyRules->pluck('settlementType'))
            ->filter();

        // Existing pivot values are retained only as a compatibility fallback
        // for installations that still use legacy accounting mappings.
        if ($ruleSettlements->isEmpty()) {
            $ruleSettlements = $head->settlementTypes->filter(fn (SettlementType $type): bool => $type->status === 'Active');
        }

        return $ruleSettlements
            ->unique('id')
            ->sortBy(fn (SettlementType $type) => sprintf('%08d-%s', (int) $type->sort_order, $type->name))
            ->values();
    }

    /**
     * @param Collection<int, AccountingRule> $activeRules
     * @param Collection<int, LedgerMappingRule> $activeLegacyRules
     */
    private function partyMode(Collection $activeRules, Collection $activeLegacyRules): string
    {
        $modes = $activeRules->pluck('party_required_mode')
            ->merge($activeLegacyRules->pluck('party_required_mode'))
            ->map(fn ($mode) => $this->normalizePartyMode((string) $mode));

        if ($modes->contains('Required')) {
            return 'Required';
        }

        return $modes->contains('Optional') ? 'Optional' : 'No';
    }

    /**
     * @param Collection<int, AccountingRule> $activeRules
     * @param Collection<int, LedgerMappingRule> $activeLegacyRules
     */
    private function cashBankRequired(Collection $activeRules, Collection $activeLegacyRules): bool
    {
        if ($activeRules->contains(function (AccountingRule $rule): bool {
            return (bool) $rule->cash_bank_ledger_required
                || (bool) $rule->payment_method_required
                || $rule->lines->contains(function (AccountingRuleLine $line): bool {
                    $source = strtolower(trim((string) $line->ledger_source));

                    return $source === 'user_cash_bank'
                        || str_contains($source, 'cash')
                        || str_contains($source, 'bank');
                });
        })) {
            return true;
        }

        return $activeLegacyRules->contains(fn (LedgerMappingRule $rule): bool =>
            (bool) $rule->cash_bank_ledger_required || (bool) $rule->payment_method_required
        );
    }

    /**
     * @param Collection<int, AccountingRule> $activeRules
     * @param Collection<int, LedgerMappingRule> $activeLegacyRules
     */
    private function transactionScreen(
        TransactionHead $head,
        Collection $activeRules,
        Collection $activeLegacyRules
    ): string {
        $configured = $activeRules->pluck('transaction_screen')
            ->merge($activeLegacyRules->pluck('transaction_screen'))
            ->filter()
            ->first();

        return $configured ?: $this->screenFromCategory($head->category);
    }

    private function normalizePartyMode(string $mode): string
    {
        return match (strtolower(trim($mode))) {
            'yes', 'required', 'require', 'always' => 'Required',
            'optional', 'allowed', 'if available' => 'Optional',
            default => 'No',
        };
    }

    private function screenFromCategory(?string $category): string
    {
        return match (TransactionHead::normaliseCategory($category)) {
            'Sales' => 'Sales Entry',
            'Purchase' => 'Purchase Entry',
            'Receipt' => 'Receipt Entry',
            'Payment' => 'Payment Entry',
            'Banking' => 'Banking Entry',
            'Expense' => 'Expense Entry',
            'Income' => 'Income Entry',
            'Owner / Equity' => 'Owner / Equity Entry',
            'Asset' => 'Asset Entry',
            'Loan' => 'Loan Entry',
            'Employee' => 'Employee Entry',
            'Opening' => 'Opening Balance Entry',
            'Adjustment' => 'Adjustment Entry',
            default => 'Transaction Entry',
        };
    }
}
