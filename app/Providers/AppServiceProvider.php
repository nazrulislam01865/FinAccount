<?php

namespace App\Providers;

use App\AccountingEngine\AccountingEngine;
use App\AccountingEngine\Contracts\AccountingEngineContract;
use App\Models\AccountingRule;
use App\Models\ReportConfiguration;
use App\Models\JournalLine;
use App\Models\JournalHeader;
use App\Models\DueRegister;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalLog;
use App\Models\AdvanceRegister;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\LedgerMappingRule;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TransactionHead;
use App\Models\User;
use App\Models\VoucherHeader;
use App\Models\VoucherNumberingRule;
use App\Observers\AuditObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (
            interface_exists(AccountingEngineContract::class)
            && class_exists(AccountingEngine::class)
        ) {
            $this->app->bind(AccountingEngineContract::class, AccountingEngine::class);
        }
    }

    public function boot(): void
    {
        $this->ensureRuntimeDirectoriesExist();

        foreach ([
            Company::class,
            FinancialYear::class,
            ChartOfAccount::class,
            CashBankAccount::class,
            Party::class,
            TransactionHead::class,
            LedgerMappingRule::class,
            AccountingRule::class,
            OpeningBalance::class,
            VoucherNumberingRule::class,
            VoucherHeader::class,
            JournalHeader::class,
            JournalLine::class,
            DueRegister::class,
            AdvanceRegister::class,
            ApprovalWorkflow::class,
            ApprovalLog::class,
            ReportConfiguration::class,
            User::class,
            Role::class,
            Permission::class,
        ] as $auditedModel) {
            if (class_exists($auditedModel)) {
                $auditedModel::observe(AuditObserver::class);
            }
        }

        /*
         * Cloud-safe HTTPS handling.
         * Do not force HTTPS unless SSL/443 is configured and APP_FORCE_HTTPS=true.
         * This prevents the Droplet browser issue where HTTP redirects to HTTPS
         * while Nginx is only listening on port 80.
         */
        if (filter_var(config('app.force_https', false), FILTER_VALIDATE_BOOLEAN)) {
            URL::forceScheme('https');
        }
    }
    /**
     * Laravel uses these runtime folders for file sessions, compiled views,
     * cache files, logs, testing artifacts and private/public uploads. ZIP
     * extraction and Git deployments can drop empty directories, so recreate
     * them before the session middleware attempts to write files.
     */
    private function ensureRuntimeDirectoriesExist(): void
    {
        foreach ([
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/testing'),
            storage_path('logs'),
            storage_path('app/private'),
            storage_path('app/public'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }
    }

}
