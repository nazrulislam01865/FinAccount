<?php

namespace App\AccountingEngine\Services;

use App\Models\AccountingRule;
use App\Models\Company;
use App\Models\LedgerMappingRule;
use App\Models\PartyType;
use Illuminate\Support\Facades\Schema;

class LegacyRuleMigrationService
{
    public function sync(LedgerMappingRule $legacyRule): ?AccountingRule
    {
        if (! Schema::hasTable('accounting_rules') || ! Schema::hasTable('accounting_rule_lines')) {
            return null;
        }

        $legacyRule->loadMissing(['transactionHead', 'settlementType']);

        $companyId = $legacyRule->company_id ?: Company::query()->orderBy('id')->value('id');

        if (! $companyId) {
            return null;
        }

        $rule = AccountingRule::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'rule_code' => $legacyRule->rule_code ?: ('AR-' . str_pad((string) $legacyRule->id, 3, '0', STR_PAD_LEFT)),
            ],
            [
                'legacy_ledger_mapping_rule_id' => $legacyRule->id,
                'rule_name' => $legacyRule->rule_name ?: $this->defaultRuleName($legacyRule),
                'transaction_head_id' => $legacyRule->transaction_head_id,
                'settlement_type_id' => $legacyRule->settlement_type_id,
                'transaction_screen' => $legacyRule->transaction_screen,
                'rule_trigger' => $legacyRule->rule_trigger ?: 'Transaction Head selected',
                'amount_required' => (bool) ($legacyRule->amount_required ?? true),
                'party_required_mode' => $legacyRule->party_required_mode ?: 'No',
                'party_type_id' => $this->partyTypeIdFromName($legacyRule->party_sub_ledger_type),
                'party_sub_ledger_type' => $legacyRule->party_sub_ledger_type,
                'payment_method_required' => (bool) ($legacyRule->payment_method_required ?? false),
                'allowed_payment_methods' => $this->paymentMethods($legacyRule->allowed_payment_method),
                'cash_bank_ledger_required' => (bool) ($legacyRule->cash_bank_ledger_required ?? false),
                'party_ledger_effect' => $legacyRule->party_ledger_effect ?: 'No Effect',
                'auto_post' => (bool) ($legacyRule->auto_post ?? true),
                'description' => $legacyRule->description,
                'status' => $legacyRule->status ?: 'Active',
                'created_by' => $legacyRule->created_by,
                'updated_by' => $legacyRule->updated_by,
            ]
        );

        $keptLineIds = [];

        foreach ([
            $this->linePayload($legacyRule, 'primary', 1),
            $this->linePayload($legacyRule, 'counter', 2),
        ] as $linePayload) {
            $line = $rule->lines()->updateOrCreate(
                [
                    'line_role' => $linePayload['line_role'],
                    'sort_order' => $linePayload['sort_order'],
                ],
                $linePayload
            );

            $keptLineIds[] = $line->id;
        }

        $rule->lines()->whereNotIn('id', $keptLineIds)->delete();

        return $rule->fresh(['lines.ledger.accountType', 'transactionHead', 'settlementType']);
    }

    /**
     * @return array<string, mixed>
     */
    private function linePayload(LedgerMappingRule $legacyRule, string $role, int $sortOrder): array
    {
        $isPrimary = $role === 'primary';
        $side = $isPrimary
            ? ($legacyRule->primary_posting_side ?: 'Debit')
            : ($legacyRule->counter_posting_side ?: 'Credit');

        $ledgerId = $isPrimary
            ? ($legacyRule->primary_ledger_id ?: ($side === 'Debit' ? $legacyRule->debit_account_id : $legacyRule->credit_account_id))
            : ($legacyRule->fixed_counter_ledger_id ?: ($side === 'Debit' ? $legacyRule->debit_account_id : $legacyRule->credit_account_id));

        return [
            'line_role' => $role,
            'ledger_source' => $this->ledgerSource($isPrimary ? $legacyRule->primary_ledger_source : $legacyRule->counter_ledger_source),
            'ledger_id' => $ledgerId,
            'side' => $side,
            'movement' => $isPrimary ? $legacyRule->primary_ledger_movement : $legacyRule->counter_ledger_movement,
            'selection_method' => $isPrimary ? null : $legacyRule->counter_selection_method,
            'allowed_ledger_type' => $isPrimary ? null : $legacyRule->allowed_counter_ledger_type,
            'amount_source' => 'transaction_amount',
            'amount_formula' => null,
            'explanation' => $isPrimary ? $legacyRule->primary_explanation : $legacyRule->counter_explanation,
            'sort_order' => $sortOrder,
        ];
    }

    private function ledgerSource(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        return match (true) {
            str_contains($source, 'cash') || str_contains($source, 'bank') || str_contains($source, 'payment method') => 'user_cash_bank',
            str_contains($source, 'party') => 'party_control',
            str_contains($source, 'transaction head') => 'transaction_head',
            str_contains($source, 'system') => 'system_derived',
            default => 'fixed',
        };
    }

    /**
     * @return array<int, string>
     */
    private function paymentMethods(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '' || strtoupper($value) === 'N/A') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function partyTypeIdFromName(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '' || strtolower($name) === 'none') {
            return null;
        }

        return PartyType::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->value('id');
    }

    private function defaultRuleName(LedgerMappingRule $legacyRule): string
    {
        return trim(($legacyRule->transactionHead?->name ?: 'Accounting Rule') . ' - ' . ($legacyRule->settlementType?->name ?: 'Posting'));
    }
}
