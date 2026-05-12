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
        if (!Schema::hasTable('companies') || !Schema::hasTable('financial_years')) {
            return null;
        }

        $company = Company::query()->first();

        if (!$company) {
            return null;
        }

        $today = Carbon::today();

        $startYear = $today->month >= 7
            ? $today->year
            : $today->year - 1;

        $startDate = Carbon::create($startYear, 7, 1)->toDateString();
        $endDate = Carbon::create($startYear + 1, 6, 30)->toDateString();

        FinancialYear::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_date', '!=', $startDate)
                    ->orWhereDate('end_date', '!=', $endDate);
            })
            ->update([
                'is_active' => false,
                'updated_by' => $userId,
            ]);

        return FinancialYear::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            [
                'name' => $startYear . '-' . ($startYear + 1),
                'is_active' => true,
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }
}
