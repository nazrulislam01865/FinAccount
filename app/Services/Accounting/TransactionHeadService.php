<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use App\Models\TransactionHead;
use App\Support\TransactionTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionHeadService
{
    public function __construct(
        private readonly AccountingOptionService $optionService,
        private readonly AutomaticCodeService $automaticCodeService,
    ) {}

    /** @return array<string, mixed> */
    public function pageData(int $companyId): array
    {
        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);

        return [
            'transactionHeads' => TransactionHead::query()
                ->with('postingAccount')
                ->where('company_id', $companyId)
                ->orderBy('category')
                ->orderBy('code')
                ->get(),
            'postingAccounts' => ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->orderBy('code')
                ->get(),
            'transactionCategories' => $transactionCategories,
            'categoryLabels' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY),
            'settlementTypes' => $this->optionService->forGroup(AccountingOption::GROUP_SETTLEMENT_TYPE),
            'settlementLabels' => $this->optionService->labels(AccountingOption::GROUP_SETTLEMENT_TYPE),
            'transactionTypeDefinitions' => $transactionCategories->mapWithKeys(fn (AccountingOption $option): array => [
                $option->value => TransactionTypes::configuredDefinition(
                    $option->value,
                    is_array($option->metadata) ? $option->metadata : [],
                    $option->label,
                ),
            ])->all(),
            'partyTypes' => $this->optionService->forGroup(AccountingOption::GROUP_RULE_PARTY_TYPE),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_RULE_PARTY_TYPE),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): TransactionHead
    {
        $this->validateSetup($data, $companyId);

        return DB::transaction(function () use ($data, $companyId): TransactionHead {
            $this->automaticCodeService->lockCompany($companyId);
            $data['code'] = $this->automaticCodeService->transactionHeadCode($companyId, (string) $data['name']);

            return TransactionHead::query()->create([
                'company_id' => $companyId,
                ...$this->normalized($data),
            ]);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(TransactionHead $head, array $data): TransactionHead
    {
        $this->validateSetup($data, (int) $head->company_id);
        DB::transaction(function () use ($head, $data): void {
            $this->automaticCodeService->lockCompany((int) $head->company_id);
            $data['code'] = (string) $data['name'] === (string) $head->name
                ? $head->code
                : $this->automaticCodeService->transactionHeadCode(
                    (int) $head->company_id,
                    (string) $data['name'],
                    (int) $head->id,
                );
            $head->update($this->normalized($data));
        }, attempts: 5);

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
    private function validateSetup(array $data, int $companyId): void
    {
        $account = ChartOfAccount::query()
            ->whereKey($data['posting_account_id'])
            ->where('company_id', $companyId)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'posting_account_id' => 'The linked account does not belong to this company.',
            ]);
        }

        $transactionType = (string) $data['category'];
        $supportedSettlements = TransactionTypes::settlementCodes();
        $selectedSettlements = array_values((array) $data['allowed_settlements']);

        if (array_diff($selectedSettlements, $supportedSettlements) !== []) {
            throw ValidationException::withMessages([
                'allowed_settlements' => 'One or more selected payment types are not supported by the system.',
            ]);
        }

        $transactionTypeOption = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', $transactionType)
            ->first();
        $transactionDefinition = TransactionTypes::configuredDefinition(
            $transactionType,
            is_array($transactionTypeOption?->metadata) ? $transactionTypeOption->metadata : [],
            $transactionTypeOption?->label,
        );
        $allowedPostingTypes = array_values((array) ($transactionDefinition['posting_types'] ?? []));
        if ($allowedPostingTypes !== [] && ! in_array($account->type, $allowedPostingTypes, true)) {
            throw ValidationException::withMessages([
                'posting_account_id' => 'Select a '.implode(' or ', $allowedPostingTypes).' account for this transaction type.',
            ]);
        }

        $expectedPartyType = (string) ($transactionDefinition['party_type'] ?? 'Any');
        if ($expectedPartyType !== 'Any' && $data['party_type'] !== $expectedPartyType) {
            throw ValidationException::withMessages([
                'party_type' => 'This transaction type uses '.$expectedPartyType.' parties.',
            ]);
        }

        if ((bool) $data['is_active'] && ! $account->is_active) {
            throw ValidationException::withMessages([
                'is_active' => 'An active transaction head must use an active linked account.',
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
            'accounting_rule_id' => null,
            'posting_account_id' => (int) $data['posting_account_id'],
            'allowed_settlements' => array_values($data['allowed_settlements']),
            'party_type' => $data['party_type'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
