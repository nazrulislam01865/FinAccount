<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use App\Models\MoneyAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoneyAccountService
{
    public function __construct(
        private readonly ChartOfAccountBalanceService $balanceService,
        private readonly AccountingOptionService $optionService,
    ) {}

    /**
     * @return array{moneyAccounts: Collection<int, MoneyAccount>, assetAccounts: Collection<int, ChartOfAccount>, balances: array<int, float>}
     */
    public function pageData(int $companyId): array
    {
        $moneyAccounts = MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $assetAccounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('type', 'Asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $accountBalances = $this->balanceService->balancesFor(
            $moneyAccounts->pluck('chartOfAccount')->filter()->values(),
            $companyId,
        );

        $balances = $moneyAccounts->mapWithKeys(fn (MoneyAccount $account): array => [
            $account->id => (float) ($accountBalances[$account->chart_of_account_id] ?? 0),
        ])->all();

        return compact('moneyAccounts', 'assetAccounts', 'balances') + [
            'moneyKinds' => $this->optionService->forGroup(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
            'moneyKindLabels' => $this->optionService->labels(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): MoneyAccount
    {
        $this->ensureAssetAccount((int) $data['chart_of_account_id'], $companyId);

        return DB::transaction(fn (): MoneyAccount => MoneyAccount::query()->create([
            'company_id' => $companyId,
            ...$this->normalized($data),
        ]), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(MoneyAccount $moneyAccount, array $data): MoneyAccount
    {
        $this->ensureAssetAccount((int) $data['chart_of_account_id'], $moneyAccount->company_id);

        if (
            $moneyAccount->chart_of_account_id !== null
            && $moneyAccount->chart_of_account_id !== (int) $data['chart_of_account_id']
            && $moneyAccount->transactions()->exists()
        ) {
            throw ValidationException::withMessages([
                'chart_of_account_id' => 'The mapped COA cannot be changed because this money account is already used by transactions.',
            ]);
        }

        DB::transaction(fn () => $moneyAccount->update($this->normalized($data)), attempts: 5);

        return $moneyAccount->refresh();
    }

    public function delete(MoneyAccount $moneyAccount): void
    {
        if ($moneyAccount->transactions()->exists()) {
            throw ValidationException::withMessages([
                'money_account' => 'Cannot delete. Used by transaction.',
            ]);
        }

        DB::transaction(fn () => $moneyAccount->delete(), attempts: 5);
    }

    private function ensureAssetAccount(int $accountId, int $companyId): void
    {
        $valid = ChartOfAccount::query()
            ->whereKey($accountId)
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('type', 'Asset')
            ->where('is_active', true)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'chart_of_account_id' => 'Select an active Level 3 Asset ledger from the Chart of Accounts list.',
            ]);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'kind' => $data['kind'],
            'chart_of_account_id' => (int) $data['chart_of_account_id'],
            'opening_balance' => $data['opening_balance'] ?? 0,
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
