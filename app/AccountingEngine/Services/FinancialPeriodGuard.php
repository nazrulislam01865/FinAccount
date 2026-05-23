<?php

namespace App\AccountingEngine\Services;

use App\Models\Company;
use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FinancialPeriodGuard
{
    public function resolveCompanyId(?int $companyId = null): int
    {
        $companyId = (int) ($companyId ?: 0);

        if ($companyId > 0) {
            return $companyId;
        }

        return (int) Company::query()->orderBy('id')->value('id');
    }

    public function resolveOpenPeriod(?int $companyId, Carbon|string $transactionDate): FinancialYear
    {
        $date = $transactionDate instanceof Carbon
            ? $transactionDate->copy()->startOfDay()
            : Carbon::parse($transactionDate)->startOfDay();

        $resolvedCompanyId = $this->resolveCompanyId($companyId);

        $query = FinancialYear::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString());

        if (Schema::hasColumn('financial_years', 'is_current')) {
            $query->orderByDesc('is_current');
        }

        $query->orderByDesc('id');

        if ($resolvedCompanyId > 0) {
            $query->where(function ($nested) use ($resolvedCompanyId) {
                $nested->where('company_id', $resolvedCompanyId)
                    ->orWhereNull('company_id');
            });
        }

        $financialYear = $query->first();

        if (! $financialYear) {
            throw ValidationException::withMessages([
                'financial_year_id' => 'No Financial Year is configured for the selected transaction date.',
            ]);
        }

        $this->assertOpen($financialYear, $date);

        return $financialYear;
    }

    public function assertOpen(FinancialYear $financialYear, Carbon|string $transactionDate): void
    {
        $date = $transactionDate instanceof Carbon
            ? $transactionDate->copy()->startOfDay()
            : Carbon::parse($transactionDate)->startOfDay();

        if ($date->lt($financialYear->start_date) || $date->gt($financialYear->end_date)) {
            throw ValidationException::withMessages([
                'voucher_date' => 'Transaction date is outside the selected Financial Year.',
            ]);
        }

        $status = (string) $financialYear->status;

        if (in_array($status, [FinancialYear::STATUS_CLOSED, FinancialYear::STATUS_LOCKED, 'Closed', 'Locked'], true)) {
            throw ValidationException::withMessages([
                'financial_year_id' => 'Financial Year is ' . $status . ' and cannot accept postings.',
            ]);
        }

        if ($financialYear->lock_date && $date->lte($financialYear->lock_date)) {
            throw ValidationException::withMessages([
                'voucher_date' => 'Transaction date is locked by the Financial Year lock date.',
            ]);
        }
    }
}
