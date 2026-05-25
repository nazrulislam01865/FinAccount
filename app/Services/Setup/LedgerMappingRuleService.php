<?php

namespace App\Services\Setup;

use App\AccountingEngine\Services\LegacyRuleMigrationService;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LedgerMappingRuleService
{
    public function __construct(
        private readonly LegacyRuleMigrationService $legacyRuleMigrationService
    ) {
    }

    public function create(array $data, ?int $userId = null): LedgerMappingRule
    {
        return DB::transaction(function () use ($data, $userId) {
            $payload = $this->payload($data, $userId, true);

            $rule = LedgerMappingRule::query()->create($payload);
            $accountingRule = $this->legacyRuleMigrationService->sync($rule);
            $this->syncSubmittedRuleLines($accountingRule, $data['rule_lines'] ?? []);

            return $this->freshRule($rule);
        });
    }

    public function update(
        LedgerMappingRule $rule,
        array $data,
        ?int $userId = null
    ): LedgerMappingRule {
        return DB::transaction(function () use ($rule, $data, $userId) {
            $rule->update($this->payload($data, $userId, false, $rule));
            $accountingRule = $this->legacyRuleMigrationService->sync($rule);
            $this->syncSubmittedRuleLines($accountingRule, $data['rule_lines'] ?? []);

            return $this->freshRule($rule);
        });
    }

    private function payload(
        array $data,
        ?int $userId,
        bool $creating,
        ?LedgerMappingRule $rule = null
    ): array {
        $payload = Arr::only($data, [
            'rule_code',
            'rule_name',
            'transaction_head_id',
            'settlement_type_id',
            'transaction_screen',
            'rule_trigger',
            'amount_required',
            'payment_method_required',
            'allowed_payment_method',
            'cash_bank_ledger_required',
            'party_required_mode',
            'party_sub_ledger_type',
            'other_required_input',
            'primary_ledger_source',
            'primary_ledger_id',
            'primary_ledger_movement',
            'primary_posting_side',
            'primary_explanation',
            'counter_ledger_source',
            'counter_selection_method',
            'fixed_counter_ledger_id',
            'allowed_counter_ledger_type',
            'counter_ledger_movement',
            'counter_posting_side',
            'counter_explanation',
            'debit_account_id',
            'credit_account_id',
            'party_ledger_effect',
            'description',
            'status',
        ]);

        $payload['company_id'] = $rule?->company_id ?: Company::query()->first()?->id;

        if ($creating) {
            $payload['rule_code'] = $payload['rule_code'] ?: $this->nextRuleCode();
        } elseif (empty($payload['rule_code'])) {
            unset($payload['rule_code']);
        }

        $payload['rule_name'] = $payload['rule_name'] ?: $this->defaultRuleName($payload);
        $payload['transaction_screen'] = $payload['transaction_screen'] ?: $this->transactionScreen($payload);
        $payload['party_ledger_effect'] = $payload['party_ledger_effect'] ?: $this->inferPartyLedgerEffect($payload);
        $payload['amount_required'] = (bool) ($data['amount_required'] ?? true);
        $payload['payment_method_required'] = (bool) ($data['payment_method_required'] ?? false);
        $payload['cash_bank_ledger_required'] = (bool) ($data['cash_bank_ledger_required'] ?? false);
        $payload['auto_post'] = (bool) ($data['auto_post'] ?? true);
        $payload['status'] = $payload['status'] ?? 'Active';

        if ($creating) {
            $payload['created_by'] = $userId;
        }

        $payload['updated_by'] = $userId;

        return $payload;
    }

    private function freshRule(LedgerMappingRule $rule): LedgerMappingRule
    {
        return $rule->fresh([
            'transactionHead',
            'settlementType',
            'primaryLedger.accountType',
            'fixedCounterLedger.accountType',
            'debitAccount.accountType',
            'creditAccount.accountType',
            'accountingRule.lines.ledger.accountType',
        ]);
    }

    private function defaultRuleName(array $payload): string
    {
        $head = TransactionHead::query()->find($payload['transaction_head_id'] ?? null);
        $settlement = SettlementType::query()->find($payload['settlement_type_id'] ?? null);

        return trim(($head?->name ?: 'Accounting Rule') . ' - ' . ($settlement?->name ?: 'Posting'));
    }

    private function transactionScreen(array $payload): ?string
    {
        return TransactionHead::query()->whereKey($payload['transaction_head_id'] ?? null)->value('transaction_screen');
    }

    private function inferPartyLedgerEffect(array $payload): string
    {
        $head = TransactionHead::query()->find($payload['transaction_head_id'] ?? null);
        $settlement = SettlementType::query()->find($payload['settlement_type_id'] ?? null);
        $debit = ChartOfAccount::query()->with('accountType')->find($payload['debit_account_id'] ?? null);
        $credit = ChartOfAccount::query()->with('accountType')->find($payload['credit_account_id'] ?? null);

        if (!$head || !$settlement || !$debit || !$credit) {
            return 'No Effect';
        }

        $settlementKey = $this->settlementKey($settlement);
        $headText = strtoupper($head->nature . ' ' . $head->name);
        $debitType = $debit->accountType?->name;
        $creditType = $credit->accountType?->name;

        if ($settlementKey === 'due') {
            if ($debitType === 'Asset' && $creditType === 'Income') {
                return 'Increase Receivable';
            }

            if ($creditType === 'Liability') {
                return 'Increase Liability';
            }
        }

        if (in_array($settlementKey, ['cash', 'bank'], true)) {
            if ($debit->is_cash_bank && $creditType === 'Asset' && $this->looksLikeReceivableCollection($headText)) {
                return 'Decrease Receivable';
            }

            if ($debit->is_cash_bank && $creditType === 'Liability' && str_contains($headText, 'ADVANCE')) {
                return 'Increase Advance Liability';
            }

            if ($debitType === 'Liability' && $credit->is_cash_bank) {
                return 'Decrease Liability';
            }

            if ($debitType === 'Asset' && $credit->is_cash_bank && str_contains($headText, 'ADVANCE')) {
                return 'Increase Advance Asset';
            }
        }

        if ($settlementKey === 'advance_paid') {
            return 'Increase Advance Asset';
        }

        if ($settlementKey === 'advance_received') {
            return 'Increase Advance Liability';
        }

        if ($settlementKey === 'adjustment') {
            if ($creditType === 'Asset' && str_contains($headText, 'ADVANCE')) {
                return 'Decrease Advance Asset';
            }

            if ($debitType === 'Liability' && str_contains($headText, 'ADVANCE')) {
                return 'Decrease Advance Liability';
            }
        }

        return 'No Effect';
    }

    private function looksLikeReceivableCollection(string $headText): bool
    {
        return str_contains($headText, 'CUSTOMER')
            || str_contains($headText, 'CLIENT')
            || str_contains($headText, 'RECEIVABLE')
            || str_contains($headText, 'RECEIVED')
            || str_contains($headText, 'COLLECTION');
    }

    private function settlementKey(SettlementType $settlement): string
    {
        $code = strtoupper((string) $settlement->code);
        $name = strtoupper((string) $settlement->name);
        $value = $code . ' ' . $name;

        return match (true) {
            str_contains($value, 'ADVANCE_PAID') || str_contains($value, 'ADVANCE PAID') => 'advance_paid',
            str_contains($value, 'ADVANCE_RECEIVED') || str_contains($value, 'ADVANCE RECEIVED') => 'advance_received',
            str_contains($value, 'CASH') => 'cash',
            str_contains($value, 'BANK') => 'bank',
            str_contains($value, 'DUE') => 'due',
            str_contains($value, 'ADJUST') => 'adjustment',
            default => 'other',
        };
    }

    private function nextRuleCode(): string
    {
        $lastCode = LedgerMappingRule::query()
            ->withTrashed()
            ->where('rule_code', 'like', 'AR-%')
            ->orderByDesc('id')
            ->value('rule_code');

        $number = $lastCode ? (int) str_replace('AR-', '', $lastCode) : 0;

        return 'AR-' . str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT);
    }
}
