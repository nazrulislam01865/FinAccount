<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\FinancialYear;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CompanyAccountingPeriodService
{
    public function defaultForCompany(Company $company): ?FinancialYear
    {
        return $company->defaultFinancialYear
            ?: FinancialYear::query()
                ->forCompany($company->id)
                ->active()
                ->where('is_current', true)
                ->orderByDesc('start_date')
                ->first();
    }

    public function forDate(Company $company, string $date): ?FinancialYear
    {
        return FinancialYear::query()
            ->forCompany($company->id)
            ->active()
            ->where('status', FinancialYear::STATUS_OPEN)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('is_current')
            ->first();
    }

    public function assertPostingAllowed(Company $company, string $date): FinancialYear
    {
        if (! $company->isActiveForPosting()) {
            throw ValidationException::withMessages([
                'company' => 'This company is inactive. Activate it in Company Setup before posting transactions.',
            ]);
        }

        if (! $company->isSetupComplete()) {
            throw ValidationException::withMessages([
                'company' => 'Complete Company Setup before posting transactions.',
            ]);
        }

        $financialYear = $this->forDate($company, $date);

        if (! $financialYear) {
            throw ValidationException::withMessages([
                'transaction_date' => 'The transaction date must belong to an active Open Financial Year.',
            ]);
        }

        $transactionDate = CarbonImmutable::parse($date)->startOfDay();
        if ($financialYear->lock_date && $transactionDate->lte($financialYear->lock_date->startOfDay())) {
            throw ValidationException::withMessages([
                'transaction_date' => 'This accounting period is locked through '.$financialYear->lock_date->format('d M Y').'.',
            ]);
        }

        return $financialYear;
    }

    /** @return array{min:?string,max:?string,default:string,label:?string} */
    public function transactionDateContext(Company $company, ?string $selectedDate = null): array
    {
        $year = $selectedDate ? $this->forDate($company, $selectedDate) : null;
        $year ??= $this->defaultForCompany($company);
        $today = now()->toDateString();

        if (! $year) {
            return ['min' => null, 'max' => null, 'default' => $today, 'label' => null];
        }

        $default = now()->between($year->start_date, $year->end_date)
            ? $today
            : $year->start_date->toDateString();

        return [
            'min' => $year->start_date->toDateString(),
            'max' => $year->end_date->toDateString(),
            'default' => $default,
            'label' => $year->name,
        ];
    }
}
