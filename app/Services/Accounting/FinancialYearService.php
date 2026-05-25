<?php

namespace App\Services\Accounting;

use App\Models\Company;
use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class FinancialYearService
{
    public function current(?int $userId = null): ?FinancialYear
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('financial_years')) {
            return null;
        }

        $company = Company::query()->first();

        if (! $company) {
            return null;
        }

        return $this->currentForCompany((int) $company->id);
    }

    public function currentForCompany(?int $companyId = null): ?FinancialYear
    {
        if (! Schema::hasTable('financial_years')) {
            return null;
        }

        $resolvedCompanyId = $this->resolveCompanyId($companyId);
        $company = $resolvedCompanyId
            ? Company::query()->find($resolvedCompanyId)
            : Company::query()->first();

        if ($company?->financial_year_start && $company?->financial_year_end) {
            $selected = FinancialYear::query()
                ->whereDate('start_date', $company->financial_year_start->toDateString())
                ->whereDate('end_date', $company->financial_year_end->toDateString())
                ->when($company->id, fn ($query) => $query->where(function ($where) use ($company) {
                    $where->where('company_id', $company->id)
                        ->orWhereNull('company_id');
                }))
                ->orderByDesc('is_current')
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first();

            if ($selected) {
                return $selected;
            }
        }

        return FinancialYear::query()
            ->when($company?->id, fn ($query) => $query->where(function ($where) use ($company) {
                $where->where('company_id', $company->id)
                    ->orWhereNull('company_id');
            }))
            ->where(function ($query) {
                $query->where('is_current', true)
                    ->orWhere('is_active', true);
            })
            ->orderByDesc('is_current')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function reportRange(?int $companyId = null): array
    {
        $financialYear = $this->currentForCompany($companyId);

        if (! $financialYear) {
            return [
                'from_date' => now()->startOfMonth()->toDateString(),
                'to_date' => now()->toDateString(),
                'financial_year' => null,
            ];
        }

        return [
            'from_date' => $financialYear->start_date->toDateString(),
            'to_date' => $financialYear->end_date->toDateString(),
            'financial_year' => $financialYear,
        ];
    }

    public function defaultTransactionDate(?int $companyId = null): string
    {
        $financialYear = $this->currentForCompany($companyId);

        if (! $financialYear) {
            return now()->toDateString();
        }

        $today = Carbon::today();

        if ($today->betweenIncluded($financialYear->start_date, $financialYear->end_date)) {
            return $today->toDateString();
        }

        return $financialYear->start_date->toDateString();
    }

    private function resolveCompanyId(?int $companyId = null): int
    {
        $companyId = (int) ($companyId ?: 0);

        if ($companyId > 0) {
            return $companyId;
        }

        return (int) Company::query()->orderBy('id')->value('id');
    }
}
