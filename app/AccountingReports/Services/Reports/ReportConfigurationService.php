<?php

namespace App\AccountingReports\Services\Reports;

use App\Models\ReportConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ReportConfigurationService
{
    public function forReport(string $reportKey, ?User $user = null): object
    {
        $fallback = (object) [
            'report_key' => $reportKey,
            'can_view' => true,
            'can_export' => true,
            'include_zero_balances' => false,
            'include_inactive_accounts' => false,
            'default_filters' => [],
        ];

        if (! Schema::hasTable('report_configurations')) {
            return $fallback;
        }

        $roleIds = $user?->activeRoles()?->pluck('roles.id')->filter()->values()->all() ?? [];

        $roleConfig = $roleIds === []
            ? null
            : ReportConfiguration::query()
                ->where('report_key', $reportKey)
                ->where('status', 'Active')
                ->whereIn('role_id', $roleIds)
                ->orderBy('can_view', 'desc')
                ->orderBy('can_export', 'desc')
                ->orderBy('sort_order')
                ->first();

        $defaultConfig = ReportConfiguration::query()
            ->where('report_key', $reportKey)
            ->where('status', 'Active')
            ->whereNull('role_id')
            ->first();

        return $roleConfig ?: $defaultConfig ?: $fallback;
    }

    public function allowZeroBalances(string $reportKey, ?User $user = null): bool
    {
        return (bool) $this->forReport($reportKey, $user)->include_zero_balances;
    }

    public function allowInactiveAccounts(string $reportKey, ?User $user = null): bool
    {
        return (bool) $this->forReport($reportKey, $user)->include_inactive_accounts;
    }

    public function canExport(string $reportKey, ?User $user = null): bool
    {
        return (bool) $this->forReport($reportKey, $user)->can_export;
    }
}
