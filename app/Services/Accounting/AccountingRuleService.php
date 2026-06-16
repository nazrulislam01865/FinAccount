<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingRuleService
{
    public function __construct(private readonly AccountingOptionService $optionService) {}

    /** @return array<string, mixed> */
    public function pageData(int $companyId): array
    {
        $sources = $this->optionService->forGroup(AccountingOption::GROUP_ACCOUNTING_SOURCE);

        $rulePartyTypes = $this->optionService->forGroup(AccountingOption::GROUP_RULE_PARTY_TYPE);

        return [
            'rules' => $this->allForCompany($companyId),
            'transactionCategories' => $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY),
            'categoryLabels' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY),
            'rulePartyTypes' => $rulePartyTypes,
            'partyTypeLabels' => $rulePartyTypes->pluck('label', 'value')->all(),
            'accountingSources' => $sources,
            'sourceLabels' => $sources->pluck('label', 'value')->all(),
        ];
    }

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
        $this->validateConfiguration($data);

        return DB::transaction(fn (): AccountingRule => AccountingRule::query()->create([
            'company_id' => $companyId,
            ...$this->normalized($data),
        ]), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(AccountingRule $rule, array $data): AccountingRule
    {
        $this->validateConfiguration($data);

        if (
            $rule->category !== $data['category']
            && $rule->transactionHeads()->where('category', '!=', $data['category'])->exists()
        ) {
            throw ValidationException::withMessages([
                'category' => 'The rule category cannot be changed while linked transaction heads use another category.',
            ]);
        }

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

    /** @param array<string, mixed> $data */
    private function validateConfiguration(array $data): void
    {
        if ($data['debit_source'] === $data['credit_source']) {
            throw ValidationException::withMessages([
                'credit_source' => 'Debit and credit sources must be different.',
            ]);
        }

        $sourceOptions = $this->optionService
            ->forGroup(AccountingOption::GROUP_ACCOUNTING_SOURCE)
            ->keyBy('value');

        $selected = collect([$data['debit_source'], $data['credit_source']])
            ->map(fn (string $source) => $sourceOptions->get($source))
            ->filter();

        if ($selected->contains(fn (AccountingOption $option): bool => (bool) ($option->metadata['requires_money'] ?? false))
            && ! (bool) $data['money_required']) {
            throw ValidationException::withMessages([
                'money_required' => 'Money account must be required because the rule uses Selected Money Account.',
            ]);
        }

        if ($selected->contains(fn (AccountingOption $option): bool => (bool) ($option->metadata['requires_party'] ?? false))
            && ! (bool) $data['party_required']) {
            throw ValidationException::withMessages([
                'party_required' => 'Party must be required because the rule uses a party receivable or payable account.',
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
            'debit_source' => $data['debit_source'],
            'credit_source' => $data['credit_source'],
            'party_required' => (bool) $data['party_required'],
            'party_type' => (bool) $data['party_required'] ? $data['party_type'] : 'Any',
            'money_required' => (bool) $data['money_required'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
