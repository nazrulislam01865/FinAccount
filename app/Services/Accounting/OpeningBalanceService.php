<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\MoneyAccount;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    /** @return array<string,mixed> */
    public function pageData(int $companyId): array
    {
        $company = Company::query()->with('defaultFinancialYear')->find($companyId);

        $openingBalances = OpeningBalance::query()
            ->with(['financialYear', 'chartOfAccount', 'party', 'moneyAccount'])
            ->where('company_id', $companyId)
            ->orderByDesc('balance_date')
            ->orderByDesc('id')
            ->get();

        $financialYears = FinancialYear::query()
            ->forCompany($companyId)
            ->active()
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->get();

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $parties = Party::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $moneyAccounts = MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $defaultYear = $company?->defaultFinancialYear
            ?: $financialYears->firstWhere('is_current', true)
            ?: $financialYears->first();

        return [
            'openingBalances' => $openingBalances,
            'financialYears' => $financialYears,
            'accounts' => $accounts,
            'parties' => $parties,
            'moneyAccounts' => $moneyAccounts,
            'statusOptions' => OpeningBalance::statusOptions(),
            'defaultFinancialYear' => $defaultYear,
            'defaultBalanceDate' => $defaultYear?->start_date?->toDateString() ?? now()->toDateString(),
        ];
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, User $user): OpeningBalance
    {
        return DB::transaction(function () use ($data, $user): OpeningBalance {
            $data = $this->normalizeLinkedFields($data, (int) $user->company_id);
            $this->validateCoaMappings($data, (int) $user->company_id);

            return OpeningBalance::query()->create([
                'company_id' => $user->company_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                ...$data,
            ]);
        }, attempts: 5);
    }

    /** @param array<string,mixed> $data */
    public function update(OpeningBalance $openingBalance, array $data, User $user): OpeningBalance
    {
        return DB::transaction(function () use ($openingBalance, $data, $user): OpeningBalance {
            $data = $this->normalizeLinkedFields($data, (int) $openingBalance->company_id);
            $this->validateCoaMappings($data, (int) $openingBalance->company_id);

            $openingBalance->update([
                'updated_by' => $user->id,
                ...$data,
            ]);

            return $openingBalance->refresh();
        }, attempts: 5);
    }

    public function delete(OpeningBalance $openingBalance): void
    {
        DB::transaction(fn () => $openingBalance->delete(), attempts: 5);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalizeLinkedFields(array $data, int $companyId): array
    {
        if (! empty($data['money_account_id'])) {
            $moneyAccount = MoneyAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->findOrFail((int) $data['money_account_id']);

            if ($moneyAccount->chart_of_account_id) {
                $data['chart_of_account_id'] = (int) $moneyAccount->chart_of_account_id;
            }
        }

        $data['financial_year_id'] = $data['financial_year_id'] ?? null;
        $data['party_id'] = $data['party_id'] ?? null;
        $data['money_account_id'] = $data['money_account_id'] ?? null;
        $data['debit'] = round((float) ($data['debit'] ?? 0), 2);
        $data['credit'] = round((float) ($data['credit'] ?? 0), 2);
        $data['reference'] = $data['reference'] ?? null;
        $data['note'] = $data['note'] ?? null;

        return $data;
    }

    /** @param array<string,mixed> $data */
    private function validateCoaMappings(array $data, int $companyId): void
    {
        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->findOrFail((int) $data['chart_of_account_id']);

        if (! empty($data['money_account_id'])) {
            $validMoney = MoneyAccount::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $data['money_account_id'])
                ->where('chart_of_account_id', $account->id)
                ->exists();

            if (! $validMoney) {
                throw ValidationException::withMessages([
                    'money_account_id' => 'Selected Money Account must be mapped with the selected COA.',
                ]);
            }
        }

        if (! empty($data['party_id'])) {
            $party = Party::query()
                ->where('company_id', $companyId)
                ->findOrFail((int) $data['party_id']);

            $isMapped = (int) $party->receivable_account_id === (int) $account->id
                || (int) $party->payable_account_id === (int) $account->id;

            if (! $isMapped) {
                throw ValidationException::withMessages([
                    'party_id' => 'Selected Party must be mapped with this receivable/payable COA.',
                ]);
            }
        }
    }

    /** @param Collection<int,Party> $parties @return array<int,float> */
    public static function postedPartyOpeningBalances(Collection $parties, int $companyId): array
    {
        if ($parties->isEmpty()) {
            return [];
        }

        return OpeningBalance::query()
            ->with('chartOfAccount:id,normal_balance')
            ->where('company_id', $companyId)
            ->where('status', OpeningBalance::STATUS_POSTED)
            ->whereIn('party_id', $parties->pluck('id'))
            ->get(['id', 'party_id', 'chart_of_account_id', 'debit', 'credit'])
            ->groupBy('party_id')
            ->map(function (Collection $lines): float {
                return round((float) $lines->sum(function (OpeningBalance $line): float {
                    $net = (float) $line->debit - (float) $line->credit;

                    return $line->chartOfAccount?->normal_balance === 'Credit' ? -$net : $net;
                }), 2);
            })
            ->all();
    }
}
