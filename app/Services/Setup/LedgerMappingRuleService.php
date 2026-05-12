<?php

namespace App\Services\Setup;

use App\Models\Company;
use App\Models\LedgerMappingRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LedgerMappingRuleService
{
    public function create(array $data, ?int $userId = null): LedgerMappingRule
    {
        return DB::transaction(function () use ($data, $userId) {
            $payload = $this->payload($data, $userId, true);

            $rule = LedgerMappingRule::query()->create($payload);

            return $rule->fresh([
                'transactionHead',
                'settlementType',
                'debitAccount.accountType',
                'creditAccount.accountType',
            ]);
        });
    }

    public function update(
        LedgerMappingRule $rule,
        array $data,
        ?int $userId = null
    ): LedgerMappingRule {
        return DB::transaction(function () use ($rule, $data, $userId) {
            $rule->update($this->payload($data, $userId, false));

            return $rule->fresh([
                'transactionHead',
                'settlementType',
                'debitAccount.accountType',
                'creditAccount.accountType',
            ]);
        });
    }

    private function payload(array $data, ?int $userId, bool $creating): array
    {
        $payload = Arr::only($data, [
            'transaction_head_id',
            'settlement_type_id',
            'debit_account_id',
            'credit_account_id',
            'party_ledger_effect',
            'description',
            'status',
        ]);

        $payload['company_id'] = Company::query()->first()?->id;
        $payload['auto_post'] = (bool) ($data['auto_post'] ?? true);

        if ($creating) {
            $payload['created_by'] = $userId;
        }

        $payload['updated_by'] = $userId;

        return $payload;
    }
}
