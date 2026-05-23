<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountingEnginePhase16IntegrationContractTest extends TestCase
{
    public function test_phase_1_to_6_accounting_flow_is_wired_together(): void
    {
        $root = dirname(__DIR__, 2);

        $controller = file_get_contents($root . '/app/Http/Controllers/TransactionController.php');
        $provider = file_get_contents($root . '/app/Providers/AppServiceProvider.php');
        $engine = file_get_contents($root . '/app/AccountingEngine/AccountingEngine.php');
        $input = file_get_contents($root . '/app/AccountingEngine/DTO/TransactionInput.php');
        $posting = file_get_contents($root . '/app/Services/Accounting/TransactionPostingService.php');
        $mapping = file_get_contents($root . '/app/Services/Accounting/MappingResolverService.php');
        $postingStorage = file_get_contents($root . '/app/AccountingEngine/Services/PostingService.php');
        $opening = file_get_contents($root . '/app/Services/Setup/OpeningBalanceService.php');
        $register = file_get_contents($root . '/app/AccountingEngine/Services/PartyRegisterService.php');
        $reportBase = file_get_contents($root . '/app/AccountingReports/Services/Reports/BaseVoucherDetailReportService.php');
        $reportService = file_get_contents($root . '/app/AccountingReports/Services/AccountingReportService.php');
        $routes = file_get_contents($root . '/routes/accounting_reports.php');

        // Phase 1: reusable engine boundary stays between controllers and posting internals.
        $this->assertStringContainsString('AccountingEngineContract', $controller);
        $this->assertStringContainsString('TransactionInput::fromRequest', $controller);
        $this->assertStringContainsString('AccountingEngineContract::class', $provider);
        $this->assertStringContainsString('TransactionPostingService', $engine);
        $this->assertStringContainsString('toLegacyPayload', $input);

        // Phase 2: company/date/rule metadata is carried into voucher detail lines.
        foreach (["'company_id'", "'transaction_date'", "'rule_line_id'", "'amount_source'"] as $field) {
            $this->assertStringContainsString($field, $postingStorage);
        }
        $this->assertStringContainsString("'company_id' =>", $input);
        $this->assertStringContainsString("'user_id' =>", $input);

        // Phase 3: V2 database-driven rules run before legacy ledger_mapping_rules fallback.
        $this->assertStringContainsString('AccountingRulePreviewService $accountingRulePreviewService', $mapping);
        $this->assertStringNotContainsString('?AccountingRulePreviewService $accountingRulePreviewService = null', $mapping);
        $this->assertStringContainsString('$this->accountingRulePreviewService->preview', $mapping);
        $this->assertStringContainsString('$v2Preview !== null', $mapping);
        $this->assertStringContainsString('$rule = $this->resolve($transactionHeadId, $settlementTypeId);', $mapping);
        $this->assertStringContainsString("'voucher_date'", $mapping);

        // Phase 4: hardened posting validations and atomic voucher creation are used.
        foreach (['FinancialPeriodGuard', 'JournalBuilder', 'JournalValidator', 'reserveWithLock', 'DB::transaction'] as $needle) {
            $this->assertStringContainsString($needle, $posting);
        }

        // Phase 5: opening balance and party registers have voucher-line traceability.
        $this->assertStringContainsString('recordOpeningBalance', $opening);
        $this->assertStringContainsString('source_voucher_detail_id', $register);
        $this->assertStringContainsString('Decrease Advance Asset', $register);
        $this->assertStringContainsString('Decrease Advance Liability', $register);

        // Phase 6: reports use voucher_details as accounting source of truth.
        $this->assertStringContainsString("DB::table('voucher_details as d')", $reportBase);
        $this->assertStringContainsString("join('voucher_headers as v'", $reportBase);
        $this->assertStringContainsString("join('chart_of_accounts as a'", $reportBase);
        $this->assertStringContainsString('balanceSheetReportService->build', $reportService);
        $this->assertStringContainsString('cashFlowStatementReportService->build', $reportService);
        $this->assertStringContainsString('customerReceivables', $reportService);
        $this->assertStringContainsString('supplierPayables', $reportService);
        $this->assertStringContainsString('salesReport', $reportService);
        $this->assertStringContainsString('expenseReport', $reportService);
        $this->assertStringContainsString("->name('accounting-reports.')", $routes);
        $this->assertStringContainsString("->name('balance-sheet.index')", $routes);
        $this->assertStringContainsString("->name('cash-flow-statement.index')", $routes);
    }

    public function test_phase_1_to_6_required_source_files_exist(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            '/app/AccountingEngine/AccountingEngine.php',
            '/app/AccountingEngine/Contracts/AccountingEngineContract.php',
            '/app/AccountingEngine/DTO/TransactionInput.php',
            '/app/AccountingEngine/Services/RuleResolver.php',
            '/app/AccountingEngine/Services/LedgerResolver.php',
            '/app/AccountingEngine/Services/AccountingRulePreviewService.php',
            '/app/AccountingEngine/Services/FinancialPeriodGuard.php',
            '/app/AccountingEngine/Services/JournalBuilder.php',
            '/app/AccountingEngine/Services/JournalValidator.php',
            '/app/AccountingEngine/Services/VoucherNumberService.php',
            '/app/AccountingEngine/Services/PostingService.php',
            '/app/AccountingEngine/Services/PartyRegisterService.php',
            '/app/AccountingEngine/Services/AuditTrailService.php',
            '/app/Services/Setup/OpeningBalanceService.php',
            '/app/AccountingReports/Services/Reports/BaseVoucherDetailReportService.php',
            '/app/AccountingReports/Services/Reports/BalanceSheetReportService.php',
            '/app/AccountingReports/Services/Reports/CashFlowStatementReportService.php',
            '/app/AccountingReports/Services/Reports/PartyBalanceReportService.php',
            '/app/AccountingReports/Services/Reports/AccountMovementReportService.php',
            '/database/migrations/2026_05_23_000002_align_phase2_schema_data_safety.php',
            '/database/migrations/2026_05_23_000004_create_phase3_accounting_rule_engine_tables.php',
            '/database/migrations/2026_05_23_000005_harden_posting_and_register_traceability.php',
            '/database/migrations/2026_05_23_000006_create_report_configurations_and_phase6_indexes.php',
        ] as $path) {
            $this->assertFileExists($root . $path);
        }
    }
}
