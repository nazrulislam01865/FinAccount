<?php

namespace App\Services\Company;

use App\Models\BusinessType;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\TimeZone;
use App\Models\User;

class CompanyMasterOverviewService
{
    /** @return array<int,array<string,mixed>> */
    public function cards(User $user): array
    {
        $companyId = (int) $user->company_id;
        $definitions = [
            [
                'title' => 'Business Types',
                'description' => 'Company classifications used by Company Setup and reporting context.',
                'icon' => '🏢',
                'route' => 'master.business-types.index',
                'permissions' => ['business_types.view', 'business_types.manage'],
                'count' => BusinessType::query()->forCompany($companyId)->count(),
            ],
            [
                'title' => 'Currencies',
                'description' => 'Currency codes, symbols, and decimal precision used throughout accounting screens.',
                'icon' => '💱',
                'route' => 'master.currencies.index',
                'permissions' => ['currencies.view', 'currencies.manage'],
                'count' => Currency::query()->forCompany($companyId)->count(),
            ],
            [
                'title' => 'Time Zones',
                'description' => 'Time zones used for company dates, transaction defaults, and displayed timestamps.',
                'icon' => '🕒',
                'route' => 'master.time-zones.index',
                'permissions' => ['time_zones.view', 'time_zones.manage'],
                'count' => TimeZone::query()->forCompany($companyId)->count(),
            ],
            [
                'title' => 'Financial Years',
                'description' => 'Open, close, lock, and select accounting periods used by transaction posting.',
                'icon' => '📅',
                'route' => 'master.financial-years.index',
                'permissions' => ['financial_years.view', 'financial_years.manage'],
                'count' => FinancialYear::query()->forCompany($companyId)->count(),
            ],
        ];

        return collect($definitions)
            ->filter(fn (array $card): bool => $user->canAnyAccounting($card['permissions']))
            ->values()
            ->all();
    }
}
