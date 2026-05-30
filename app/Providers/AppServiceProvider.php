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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
        $this->configureRateLimiters();

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
     * Named rate limiters keep public forms and isolated Landing Admin login
     * protected from brute-force and spam without affecting normal accounting
     * page navigation.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('landing-inquiry', function (Request $request): Limit {
            return Limit::perMinute((int) config('security.rate_limits.landing_inquiry_per_minute', 5))
                ->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('landing-admin-login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute((int) config('security.rate_limits.landing_admin_login_per_minute', 5))
                ->by($email . '|' . ($request->ip() ?: 'unknown'));
        });

        RateLimiter::for('web-forms', function (Request $request): Limit {
            return Limit::perMinute((int) config('security.rate_limits.web_forms_per_minute', 30))
                ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip() ?: 'guest'));
        });
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
