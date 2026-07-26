<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\FinancialYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CompanyAccountingPeriodService
{
    public function defaultForCompany(Company $company): ?FinancialYear
    {
        $default = $company->defaultFinancialYear;

        if ($default
            && (bool) $default->is_active
            && $default->status === FinancialYear::STATUS_OPEN) {
            return $default;
        }

        return FinancialYear::query()
            ->forCompany($company->id)
            ->active()
            ->where('status', FinancialYear::STATUS_OPEN)
            ->orderByDesc('is_current')
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

    /**
     * @return array{
     *     min:?string,
     *     max:?string,
     *     default:string,
     *     label:?string,
     *     today:string,
     *     backdated_enabled:bool,
     *     ranges:array<int, array{id:int,name:string,start:string,end:string}>
     * }
     */
    public function transactionDateContext(Company $company, ?string $selectedDate = null): array
    {
        $today = now()->toDateString();
        $ranges = $this->postingRanges($company);

        if ($ranges->isEmpty()) {
            return [
                'min' => null,
                'max' => null,
                'default' => $selectedDate ?: $today,
                'label' => null,
                'today' => $today,
                'backdated_enabled' => false,
                'ranges' => [],
            ];
        }

        $selectedRange = $selectedDate
            ? $ranges->first(fn (array $range): bool => $selectedDate >= $range['start'] && $selectedDate <= $range['end'])
            : null;
        $todayRange = $ranges->first(fn (array $range): bool => $today >= $range['start'] && $today <= $range['end']);
        $defaultFinancialYearId = (int) ($company->default_financial_year_id ?? 0);
        $companyDefaultRange = $ranges->first(fn (array $range): bool => $range['id'] === $defaultFinancialYearId);
        $preferredRange = $selectedRange
            ?? $todayRange
            ?? $companyDefaultRange
            ?? $ranges->sortByDesc('start')->first();

        $default = $selectedRange && $selectedDate
            ? $selectedDate
            : ($todayRange ? $today : (string) $preferredRange['start']);

        return [
            'min' => (string) $ranges->min('start'),
            'max' => (string) $ranges->max('end'),
            'default' => $default,
            'label' => $ranges->count() === 1
                ? (string) $ranges->first()['name']
                : $ranges->count().' open financial years',
            'today' => $today,
            'backdated_enabled' => $ranges->contains(fn (array $range): bool => $range['start'] < $today),
            'ranges' => $ranges->values()->all(),
        ];
    }

    /** @return Collection<int, array{id:int,name:string,start:string,end:string}> */
    private function postingRanges(Company $company): Collection
    {
        return FinancialYear::query()
            ->forCompany($company->id)
            ->active()
            ->where('status', FinancialYear::STATUS_OPEN)
            ->orderBy('start_date')
            ->get()
            ->map(function (FinancialYear $year): ?array {
                $start = CarbonImmutable::parse($year->start_date)->startOfDay();
                $end = CarbonImmutable::parse($year->end_date)->startOfDay();

                if ($year->lock_date) {
                    $firstUnlockedDate = CarbonImmutable::parse($year->lock_date)->startOfDay()->addDay();
                    if ($firstUnlockedDate->gt($start)) {
                        $start = $firstUnlockedDate;
                    }
                }

                if ($start->gt($end)) {
                    return null;
                }

                return [
                    'id' => (int) $year->id,
                    'name' => (string) $year->name,
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ];
            })
            ->filter()
            ->values();
    }
}
