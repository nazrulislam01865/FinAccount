<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AccountingReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configPath = config_path('accounting_reports.php');

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'accounting_reports');
        }
    }

    public function boot(): void
    {
        if (file_exists(base_path('routes/accounting_reports.php'))) {
            $this->loadRoutesFrom(base_path('routes/accounting_reports.php'));
        }

        if (is_dir(resource_path('views/accounting_reports'))) {
            $this->loadViewsFrom(resource_path('views/accounting_reports'), 'accounting_reports');
        }
    }
}
