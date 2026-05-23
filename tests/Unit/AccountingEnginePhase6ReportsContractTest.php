<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountingEnginePhase6ReportsContractTest extends TestCase
{
    public function test_phase_6_report_source_files_exist(): void
    {
        foreach ([
            '/app/Models/ReportConfiguration.php',
            '/app/AccountingReports/Services/Reports/BaseVoucherDetailReportService.php',
            '/app/AccountingReports/Services/Reports/ReportConfigurationService.php',
            '/app/AccountingReports/Services/Reports/BalanceSheetReportService.php',
            '/app/AccountingReports/Services/Reports/CashFlowStatementReportService.php',
            '/app/AccountingReports/Services/Reports/PartyBalanceReportService.php',
            '/app/AccountingReports/Services/Reports/AccountMovementReportService.php',
            '/database/migrations/2026_05_23_000006_create_report_configurations_and_phase6_indexes.php',
        ] as $path) {
            $this->assertFileExists(dirname(__DIR__, 2) . $path);
        }
    }

    public function test_reports_are_standardized_on_voucher_details(): void
    {
        $root = dirname(__DIR__, 2);

        $base = file_get_contents($root . '/app/AccountingReports/Services/Reports/BaseVoucherDetailReportService.php');
        $accountingReport = file_get_contents($root . '/app/AccountingReports/Services/AccountingReportService.php');
        $routes = file_get_contents($root . '/routes/accounting_reports.php');

        $this->assertStringContainsString("DB::table('voucher_details as d')", $base);
        $this->assertStringContainsString("join('voucher_headers as v'", $base);
        $this->assertStringContainsString("join('chart_of_accounts as a'", $base);

        $this->assertStringContainsString('balanceSheetReportService->build', $accountingReport);
        $this->assertStringContainsString('cashFlowStatementReportService->build', $accountingReport);
        $this->assertStringContainsString('customerReceivables', $accountingReport);
        $this->assertStringContainsString('supplierPayables', $accountingReport);
        $this->assertStringContainsString('salesReport', $accountingReport);
        $this->assertStringContainsString('expenseReport', $accountingReport);

        // Laravel builds final names from this group prefix.
        // Example: accounting-reports. + balance-sheet.index
        // Final: accounting-reports.balance-sheet.index
        $this->assertStringContainsString("->name('accounting-reports.')", $routes);

        foreach ([
            "->name('balance-sheet.index')",
            "->name('balance-sheet.export')",
            "->name('cash-flow-statement.index')",
            "->name('cash-flow-statement.export')",
            "->name('customer-receivables.index')",
            "->name('customer-receivables.export')",
            "->name('supplier-payables.index')",
            "->name('supplier-payables.export')",
            "->name('sales-report.index')",
            "->name('sales-report.export')",
            "->name('expense-report.index')",
            "->name('expense-report.export')",
        ] as $routeName) {
            $this->assertStringContainsString($routeName, $routes);
        }
    }
}