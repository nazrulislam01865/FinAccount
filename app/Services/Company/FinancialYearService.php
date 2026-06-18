<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialYearService
{
    /** @return array{financialYears:Collection<int,FinancialYear>,transactionUsage:array<int,int>,statusOptions:array<string,string>} */
    public function pageData(int $companyId): array
    {
        $financialYears = FinancialYear::query()->forCompany($companyId)->orderByDesc('start_date')->get();
        $transactionUsage = $financialYears->mapWithKeys(fn (FinancialYear $year): array => [
            $year->id => $this->transactionCount($year),
        ])->all();

        return [
            'financialYears' => $financialYears,
            'transactionUsage' => $transactionUsage,
            'statusOptions' => FinancialYear::statusOptions(),
        ];
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $companyId, User $user): FinancialYear
    {
        $this->assertCurrentStateIsValid($data);

        return DB::transaction(function () use ($data, $companyId, $user): FinancialYear {
            Company::query()->lockForUpdate()->findOrFail($companyId);
            $this->assertNoOverlap($companyId, $data['start_date'], $data['end_date']);

            if ($data['is_current']) {
                FinancialYear::query()
                    ->forCompany($companyId)
                    ->update(['is_current' => false, 'updated_by' => $user->id]);
            }

            $year = FinancialYear::query()->create([
                'company_id' => $companyId,
                ...$this->normalized($data),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($year->is_current) {
                Company::query()->whereKey($companyId)->update([
                    'default_financial_year_id' => $year->id,
                    'setup_completed_at' => now(),
                    'updated_by' => $user->id,
                ]);
            }

            return $year;
        }, attempts: 5);
    }

    /** @param array<string,mixed> $data */
    public function update(FinancialYear $financialYear, array $data, User $user): FinancialYear
    {
        $this->assertCurrentStateIsValid($data);

        return DB::transaction(function () use ($financialYear, $data, $user): FinancialYear {
            Company::query()->lockForUpdate()->findOrFail($financialYear->company_id);
            $financialYear = FinancialYear::query()->lockForUpdate()->findOrFail($financialYear->id);

            $this->assertNoOverlap(
                $financialYear->company_id,
                $data['start_date'],
                $data['end_date'],
                $financialYear->id,
            );

            $companyUsesYear = Company::query()
                ->whereKey($financialYear->company_id)
                ->where('default_financial_year_id', $financialYear->id)
                ->exists();

            if ($companyUsesYear && (
                ! $data['is_current']
                || ! $data['is_active']
                || $data['status'] !== FinancialYear::STATUS_OPEN
            )) {
                throw ValidationException::withMessages([
                    'status' => 'The Financial Year selected in Company Setup must remain Current, Active, and Open. Select another current year first.',
                ]);
            }

            $dateRangeChanged = $financialYear->start_date->toDateString() !== $data['start_date']
                || $financialYear->end_date->toDateString() !== $data['end_date'];

            if ($dateRangeChanged && $this->transactionCount($financialYear) > 0) {
                throw ValidationException::withMessages([
                    'start_date' => 'The date range cannot be changed because transactions exist inside this Financial Year.',
                ]);
            }

            if ($data['is_current']) {
                FinancialYear::query()
                    ->forCompany($financialYear->company_id)
                    ->whereKeyNot($financialYear->id)
                    ->update(['is_current' => false, 'updated_by' => $user->id]);
            }

            $financialYear->update([
                ...$this->normalized($data),
                'updated_by' => $user->id,
            ]);

            if ($financialYear->is_current) {
                Company::query()->whereKey($financialYear->company_id)->update([
                    'default_financial_year_id' => $financialYear->id,
                    'setup_completed_at' => now(),
                    'updated_by' => $user->id,
                ]);
            }

            return $financialYear->refresh();
        }, attempts: 5);
    }

    /** @param array<string,mixed> $data */
    private function assertCurrentStateIsValid(array $data): void
    {
        if ($data['is_current'] && (
            ! $data['is_active']
            || $data['status'] !== FinancialYear::STATUS_OPEN
        )) {
            throw ValidationException::withMessages([
                'status' => 'A Current Financial Year must be Active and Open.',
            ]);
        }
    }

    private function assertNoOverlap(int $companyId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $overlaps = FinancialYear::query()
            ->forCompany($companyId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => 'Financial Year dates cannot overlap another Financial Year.',
            ]);
        }
    }

    private function transactionCount(FinancialYear $financialYear): int
    {
        return Transaction::query()
            ->where('company_id', $financialYear->company_id)
            ->whereBetween('transaction_date', [
                $financialYear->start_date->toDateString(),
                $financialYear->end_date->toDateString(),
            ])->count();
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalized(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'lock_date' => $data['lock_date'] ?: null,
            'is_active' => (bool) $data['is_active'],
            'is_current' => (bool) $data['is_current'],
            'status' => $data['status'],
        ];
    }
}
