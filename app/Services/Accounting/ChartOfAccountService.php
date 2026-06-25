<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountService
{
    public function __construct(
        private readonly ChartOfAccountBalanceService $balanceService,
        private readonly AccountingOptionService $optionService,
        private readonly AutomaticCodeService $automaticCodeService,
    ) {}

    /** @return array<string, mixed> */
    public function pageData(int $companyId, string $search = '', int $modalAccountId = 0): array
    {
        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->get();

        return [
            'accounts' => $accounts,
            'balances' => $this->balanceService->balancesFor($accounts, $companyId),
            'search' => $search,
            'modalAccount' => $modalAccountId > 0
                ? ChartOfAccount::query()->where('company_id', $companyId)->find($modalAccountId)
                : null,
            'accountTypes' => $this->optionService->forGroup(AccountingOption::GROUP_ACCOUNT_TYPE),
            'normalBalances' => $this->optionService->forGroup(AccountingOption::GROUP_NORMAL_BALANCE),
            'nextCodes' => collect($this->optionService->forGroup(AccountingOption::GROUP_ACCOUNT_TYPE))
                ->mapWithKeys(fn (AccountingOption $option): array => [
                    $option->value => $this->automaticCodeService->nextChartOfAccountCode($companyId, $option->value),
                ])->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): ChartOfAccount
    {
        return DB::transaction(function () use ($data, $companyId): ChartOfAccount {
            $this->automaticCodeService->lockCompany($companyId);
            $data['code'] = $this->automaticCodeService->nextChartOfAccountCode($companyId, (string) $data['type']);

            return ChartOfAccount::query()->create([
                'company_id' => $companyId,
                ...$data,
            ]);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(ChartOfAccount $account, array $data): ChartOfAccount
    {
        $this->validateMappedAccountType($account, (string) $data['type']);

        DB::transaction(function () use ($account, $data): void {
            $this->automaticCodeService->lockCompany((int) $account->company_id);

            if ((string) $data['type'] !== (string) $account->type) {
                $data['code'] = $this->automaticCodeService->nextChartOfAccountCode(
                    (int) $account->company_id,
                    (string) $data['type'],
                    (int) $account->id,
                );
            } else {
                $data['code'] = $account->code;
            }

            $account->update($data);
        }, attempts: 5);

        return $account->refresh();
    }

    public function delete(ChartOfAccount $account): void
    {
        $uses = collect([
            'money account' => $account->moneyAccounts()->exists(),
            'party' => $account->receivableParties()->exists() || $account->payableParties()->exists(),
            'transaction head' => $account->transactionHeads()->exists(),
            'journal' => $account->journalLines()->exists(),
        ])->filter()->keys();

        if ($uses->isNotEmpty()) {
            throw ValidationException::withMessages([
                'account' => 'Cannot delete. Used by '.$uses->implode(', ').'.',
            ]);
        }

        DB::transaction(fn () => $account->delete(), attempts: 5);
    }

    private function validateMappedAccountType(ChartOfAccount $account, string $newType): void
    {
        if (($account->moneyAccounts()->exists() || $account->receivableParties()->exists()) && $newType !== 'Asset') {
            throw ValidationException::withMessages([
                'type' => 'This account must remain an Asset because it is mapped to a money account or party receivable.',
            ]);
        }

        if ($account->payableParties()->exists() && ! in_array($newType, ['Liability', 'Equity'], true)) {
            throw ValidationException::withMessages([
                'type' => 'This account must remain a Liability or Equity account because it is mapped to party payable/capital.',
            ]);
        }
    }
}
