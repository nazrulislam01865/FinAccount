<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountingEnginePhase710ProductionUxContractTest extends TestCase
{
    public function test_phase_7_dashboard_uses_posted_voucher_lines_and_caching(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root.'/app/Services/Dashboard/DashboardService.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/DashboardController.php');
        $view = file_get_contents($root.'/resources/views/dashboard.blade.php');
        $routes = file_get_contents($root.'/routes/web.php');

        $this->assertStringContainsString('Cache::remember', $service);
        $this->assertStringContainsString("DB::table('voucher_details as d')", $service);
        $this->assertStringContainsString('VoucherHeader::STATUS_POSTED', $service);
        $this->assertStringContainsString('monthly_income', $service);
        $this->assertStringContainsString('total_receivable', $service);
        $this->assertStringContainsString('pending_approvals', $service);
        $this->assertStringContainsString('DashboardService', $controller);
        $this->assertStringContainsString("->name('dashboard')", $routes);
        $this->assertStringContainsString('Business Overview', $view);
        $this->assertStringContainsString('Recent Transactions', $view);
    }

    public function test_phase_8_approval_rbac_and_audit_trail_are_wired(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_05_24_000002_create_phase8_approval_and_audit_tables.php');
        $posting = file_get_contents($root.'/app/Services/Accounting/TransactionPostingService.php');
        $approval = file_get_contents($root.'/app/Services/Approval/ApprovalWorkflowService.php');
        $audit = file_get_contents($root.'/app/AccountingEngine/Services/AuditTrailService.php');
        $observer = file_get_contents($root.'/app/Providers/AppServiceProvider.php');
        $routes = file_get_contents($root.'/routes/web.php');
        $access = file_get_contents($root.'/config/access.php');

        foreach (['approval_workflows', 'approval_logs', 'lifecycle_state', 'company_id', 'ip_address', 'user_agent'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString('ApprovalWorkflowService', $posting);
        $this->assertStringContainsString('STATUS_PENDING_REVIEW', $posting);
        $this->assertStringContainsString('shouldSubmitForApproval', $posting);
        $this->assertStringContainsString('approveAndPost', $approval);
        $this->assertStringContainsString('recordPostedVoucher', $approval);
        $this->assertStringContainsString('Schema::hasTable', $audit);
        $this->assertStringContainsString('AuditObserver', $observer);
        $this->assertStringContainsString("Route::get('/approvals'", $routes);
        $this->assertStringContainsString("Route::get('/audit-trail'", $routes);
        $this->assertStringContainsString("'approvals.manage'", $access);
        $this->assertStringContainsString("'audit-trail.view'", $access);
    }

    public function test_phase_9_production_hardening_hooks_exist(): void
    {
        $root = dirname(__DIR__, 2);
        $health = file_get_contents($root.'/app/Http/Controllers/System/HealthController.php');
        $middleware = file_get_contents($root.'/app/Http/Middleware/SessionTimeout.php');
        $bootstrap = file_get_contents($root.'/bootstrap/app.php');
        $session = file_get_contents($root.'/config/session.php');
        $posting = file_get_contents($root.'/app/AccountingEngine/Services/PostingService.php');
        $request = file_get_contents($root.'/app/Http/Requests/TransactionEntryRequest.php');
        $console = file_get_contents($root.'/routes/console.php');

        $this->assertStringContainsString('select 1', $health);
        $this->assertStringContainsString("Route::get('/health'", file_get_contents($root.'/routes/web.php'));
        $this->assertStringContainsString('SessionTimeout', $bootstrap);
        $this->assertStringContainsString('inactive_timeout', $session);
        $this->assertStringContainsString("store('voucher-attachments', 'local')", $posting);
        $this->assertStringContainsString('mimes:pdf,jpg,jpeg,png', $request);
        $this->assertStringContainsString('max:5120', $request);
        $this->assertStringContainsString('accounting:backup-database', $console);
        $this->assertStringContainsString('accounting:backup-files', $console);
        $this->assertStringContainsString("dailyAt('02:00')", $console);
    }

    public function test_phase_10_qa_contract_covers_phase_7_to_10_rollout_items(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/tests/Unit/AccountingEnginePhase710ProductionUxContractTest.php');

        $releaseNotes = file_get_contents($root.'/docs/phase-7-to-10-production-rollout.md');

        foreach (['Dashboard', 'Approval', 'Audit', 'Backup', 'UAT', 'Rollback'] as $needle) {
            $this->assertStringContainsString($needle, $releaseNotes);
        }
    }
}
