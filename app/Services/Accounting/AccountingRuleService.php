<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingRuleService
{
    /** @return Collection<int, AccountingRule> */
    public function allForCompany(int $companyId): Collection
    {
        return AccountingRule::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): AccountingRule
    {
        return DB::transaction(fn (): AccountingRule => AccountingRule::query()->create([
            'company_id' => $companyId,
            ...$this->normalized($data),
        ]), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(AccountingRule $rule, array $data): AccountingRule
    {
        DB::transaction(fn () => $rule->update($this->normalized($data)), attempts: 5);

        return $rule->refresh();
    }

    public function delete(AccountingRule $rule): void
    {
        if ($rule->transactionHeads()->exists()) {
            throw ValidationException::withMessages([
                'accounting_rule' => 'Cannot delete. Linked to transaction head.',
            ]);
        }

        DB::transaction(fn () => $rule->delete(), attempts: 5);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data): array
    {
        return [
            'code' => trim((string) $data['code']),
            'name' => trim((string) $data['name']),
            'category' => $data['category'],
            'debit_source' => $data['debit_source'],
            'credit_source' => $data['credit_source'],
            'party_required' => (bool) $data['party_required'],
            'party_type' => $data['party_type'],
            'money_required' => (bool) $data['money_required'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
