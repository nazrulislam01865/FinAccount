<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\TransactionHead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionHeadService
{
    /**
     * @return array{transactionHeads: Collection<int, TransactionHead>, accountingRules: Collection<int, AccountingRule>, postingAccounts: Collection<int, ChartOfAccount>}
     */
    public function pageData(int $companyId): array
    {
        return [
            'transactionHeads' => TransactionHead::query()
                ->with(['accountingRule', 'postingAccount'])
                ->where('company_id', $companyId)
                ->orderBy('code')
                ->get(),
            'accountingRules' => AccountingRule::query()
                ->where('company_id', $companyId)
                ->orderBy('code')
                ->get(),
            'postingAccounts' => ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->orderBy('code')
                ->get(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): TransactionHead
    {
        $this->validateRuleCategory($data, $companyId);

        return DB::transaction(fn (): TransactionHead => TransactionHead::query()->create([
            'company_id' => $companyId,
            ...$this->normalized($data),
        ]), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(TransactionHead $head, array $data): TransactionHead
    {
        $this->validateRuleCategory($data, $head->company_id);
        DB::transaction(fn () => $head->update($this->normalized($data)), attempts: 5);

        return $head->refresh();
    }

    public function delete(TransactionHead $head): void
    {
        if ($head->transactions()->exists()) {
            throw ValidationException::withMessages([
                'transaction_head' => 'Cannot delete. Used by transaction.',
            ]);
        }

        DB::transaction(fn () => $head->delete(), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    private function validateRuleCategory(array $data, int $companyId): void
    {
        $valid = AccountingRule::query()
            ->whereKey($data['accounting_rule_id'])
            ->where('company_id', $companyId)
            ->where('category', $data['category'])
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'accounting_rule_id' => 'The accounting rule category must match the transaction head category.',
            ]);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data): array
    {
        return [
            'code' => trim((string) $data['code']),
            'name' => trim((string) $data['name']),
            'category' => $data['category'],
            'accounting_rule_id' => (int) $data['accounting_rule_id'],
            'posting_account_id' => (int) $data['posting_account_id'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
