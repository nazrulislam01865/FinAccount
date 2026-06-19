<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
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
            'amountBasisLabels' => $this->amountBasisLabels(),
        ];
    }

    /** @return Collection<int, AccountingRule> */
    public function allForCompany(int $companyId): Collection
    {
        return AccountingRule::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): AccountingRule
    {
        $this->validateConfiguration($data);

        return DB::transaction(function () use ($data, $companyId): AccountingRule {
            $rule = AccountingRule::query()->create([
                'company_id' => $companyId,
                ...$this->normalized($data),
            ]);

            $this->syncLines($rule, $data['lines']);

            return $rule->load('lines');
        }, attempts: 5);
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

        DB::transaction(function () use ($rule, $data): void {
            $rule->update($this->normalized($data));
            $this->syncLines($rule, $data['lines']);
        }, attempts: 5);

        return $rule->refresh()->load('lines');
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
        $lines = collect($data['lines'] ?? [])->values();

        if ($lines->count() < 2) {
            throw ValidationException::withMessages([
                'lines' => 'At least one debit line and one credit line are required.',
            ]);
        }

        if (! $lines->contains('line_side', AccountingRuleLine::SIDE_DEBIT)) {
            throw ValidationException::withMessages([
                'lines' => 'At least one debit posting line is required.',
            ]);
        }

        if (! $lines->contains('line_side', AccountingRuleLine::SIDE_CREDIT)) {
            throw ValidationException::withMessages([
                'lines' => 'At least one credit posting line is required.',
            ]);
        }

        $this->assertAmountFormulaBalances($lines);

        if ((bool) ($data['generates_invoice'] ?? false) && ($data['category'] ?? null) !== 'Sales') {
            throw ValidationException::withMessages([
                'generates_invoice' => 'Only sales category accounting rules can generate sales invoices.',
            ]);
        }

        $sourceOptions = $this->optionService
            ->forGroup(AccountingOption::GROUP_ACCOUNTING_SOURCE)
            ->keyBy('value');

        $selected = $lines
            ->map(fn (array $line) => $sourceOptions->get($line['account_source']))
            ->filter();

        $requiresMoney = $selected->contains(fn (AccountingOption $option): bool => (bool) ($option->metadata['requires_money'] ?? false));
        $requiresParty = $selected->contains(fn (AccountingOption $option): bool => (bool) ($option->metadata['requires_party'] ?? false));

        if ($requiresMoney && ! (bool) $data['money_required']) {
            throw ValidationException::withMessages([
                'money_required' => 'Money account must be required because at least one posting line uses Selected Money Account.',
            ]);
        }

        if ($requiresParty && ! (bool) $data['party_required']) {
            throw ValidationException::withMessages([
                'party_required' => 'Party must be required because at least one posting line uses Party Receivable or Party Payable.',
            ]);
        }
    }

    /** @param Collection<int, array{line_side: string, account_source: string, amount_basis: string}> $lines */
    private function assertAmountFormulaBalances(Collection $lines): void
    {
        $debitPaid = 0;
        $debitDue = 0;
        $creditPaid = 0;
        $creditDue = 0;

        foreach ($lines as $line) {
            [$paidCoefficient, $dueCoefficient] = match ($line['amount_basis']) {
                AccountingRuleLine::BASIS_TOTAL => [1, 1],
                AccountingRuleLine::BASIS_PAID => [1, 0],
                AccountingRuleLine::BASIS_DUE => [0, 1],
                default => [0, 0],
            };

            if ($line['line_side'] === AccountingRuleLine::SIDE_DEBIT) {
                $debitPaid += $paidCoefficient;
                $debitDue += $dueCoefficient;
            } else {
                $creditPaid += $paidCoefficient;
                $creditDue += $dueCoefficient;
            }
        }

        if ($debitPaid !== $creditPaid || $debitDue !== $creditDue) {
            throw ValidationException::withMessages([
                'lines' => 'Posting lines are not balanced. Debit amount formula must equal credit amount formula. Example: paid + due on one side must equal total on the other side.',
            ]);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data): array
    {
        $lines = collect($data['lines']);
        $firstDebit = $lines->firstWhere('line_side', AccountingRuleLine::SIDE_DEBIT);
        $firstCredit = $lines->firstWhere('line_side', AccountingRuleLine::SIDE_CREDIT);

        $sourceOptions = $this->optionService
            ->forGroup(AccountingOption::GROUP_ACCOUNTING_SOURCE)
            ->keyBy('value');

        $selected = $lines
            ->map(fn (array $line) => $sourceOptions->get($line['account_source']))
            ->filter();

        $moneyRequired = $selected->contains(fn (AccountingOption $option): bool => (bool) ($option->metadata['requires_money'] ?? false));
        $partyRequired = $selected->contains(fn (AccountingOption $option): bool => (bool) ($option->metadata['requires_party'] ?? false));

        return [
            'code' => trim((string) $data['code']),
            'name' => trim((string) $data['name']),
            'category' => $data['category'],
            'debit_source' => $firstDebit['account_source'],
            'credit_source' => $firstCredit['account_source'],
            'party_required' => $partyRequired,
            'party_type' => $partyRequired ? $data['party_type'] : 'Any',
            'money_required' => $moneyRequired,
            'generates_invoice' => (bool) ($data['generates_invoice'] ?? false),
            'invoice_title' => filled($data['invoice_title'] ?? null) ? trim((string) $data['invoice_title']) : null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /** @param array<int, array{line_side: string, account_source: string, amount_basis: string}> $lines */
    private function syncLines(AccountingRule $rule, array $lines): void
    {
        $rule->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $rule->lines()->create([
                'line_side' => $line['line_side'],
                'account_source' => $line['account_source'],
                'amount_basis' => $line['amount_basis'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /** @return array<string, string> */
    public function amountBasisLabels(): array
    {
        return [
            AccountingRuleLine::BASIS_TOTAL => 'Total Amount',
            AccountingRuleLine::BASIS_PAID => 'Paid Amount',
            AccountingRuleLine::BASIS_DUE => 'Due Amount',
        ];
    }
}
