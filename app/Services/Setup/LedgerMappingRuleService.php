<?php

namespace App\Services\Setup;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LedgerMappingRuleService
{
    public function create(array $data, ?int $userId = null): LedgerMappingRule
    {
        return DB::transaction(function () use ($data, $userId) {
            $payload = $this->payload($data, $userId, true);

            $rule = LedgerMappingRule::query()->create($payload);

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
            'transaction_head_id',
            'settlement_type_id',
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

        $payload['party_ledger_effect'] = $payload['party_ledger_effect']
            ?: $this->inferPartyLedgerEffect($payload);

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
            'debitAccount.accountType',
            'creditAccount.accountType',
        ]);
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
            ->where('rule_code', 'like', 'LM-%')
            ->orderByDesc('id')
            ->value('rule_code');

        $number = $lastCode ? (int) str_replace('LM-', '', $lastCode) : 0;

        return 'LM-' . str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT);
    }
}
