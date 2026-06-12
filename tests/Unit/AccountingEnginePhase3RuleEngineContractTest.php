<?php

namespace Tests\Unit;

use App\AccountingEngine\Services\LedgerResolver;
use PHPUnit\Framework\TestCase;

class AccountingEnginePhase3RuleEngineContractTest extends TestCase
{
    public function test_phase_3_migration_contains_rule_engine_tables_and_required_columns(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_05_23_000004_create_phase3_accounting_rule_engine_tables.php');

        foreach ([
            'accounting_rules',
            'accounting_rule_lines',
            'legacy_ledger_mapping_rule_id',
            'rule_code',
            'transaction_head_id',
            'settlement_type_id',
            'party_required_mode',
            'allowed_payment_methods',
            'line_role',
            'ledger_source',
            'amount_source',
            'backfillFromLegacyLedgerMappingRules',
        ] as $expected) {
            $this->assertStringContainsString($expected, $migration);
        }
    }

    public function test_ledger_resolver_normalizes_excel_and_srs_ledger_sources(): void
    {
        $resolver = new LedgerResolver();

        $this->assertSame('fixed', $resolver->normalizeLedgerSource('Fixed Ledger'));
        $this->assertSame('user_cash_bank', $resolver->normalizeLedgerSource('User Selected Cash/Bank Ledger'));
        $this->assertSame('party_control', $resolver->normalizeLedgerSource('User Selected Party Control Ledger'));
        $this->assertSame('party_receivable', $resolver->normalizeLedgerSource('Party Receivable Ledger'));
        $this->assertSame('party_payable', $resolver->normalizeLedgerSource('Party Payable Ledger'));
        $this->assertSame('party_advance_paid', $resolver->normalizeLedgerSource('Party Advance Paid Ledger'));
        $this->assertSame('party_advance_received', $resolver->normalizeLedgerSource('Party Advance Received Ledger'));
        $this->assertSame('party_loan_payable', $resolver->normalizeLedgerSource('Party Loan Payable Ledger'));
        $this->assertSame('party_salary_payable', $resolver->normalizeLedgerSource('Party Salary Payable Ledger'));
        $this->assertSame('party_capital', $resolver->normalizeLedgerSource('Party Capital Ledger'));
        $this->assertSame('transaction_head', $resolver->normalizeLedgerSource('Transaction Head Based Ledger'));
        $this->assertSame('system_derived', $resolver->normalizeLedgerSource('System Derived Ledger'));
    }

    public function test_phase_3_services_exist_for_v2_lookup_and_legacy_fallback(): void
    {
        foreach ([
            '/app/AccountingEngine/Services/RuleResolver.php' => 'AccountingRule|LedgerMappingRule',
            '/app/AccountingEngine/Services/LedgerResolver.php' => 'user_cash_bank',
            '/app/AccountingEngine/Services/AccountingRulePreviewService.php' => 'buildPreview',
            '/app/AccountingEngine/Services/LegacyRuleMigrationService.php' => 'legacy_ledger_mapping_rule_id',
        ] as $path => $needle) {
            $contents = file_get_contents(dirname(__DIR__, 2) . $path);
            $this->assertStringContainsString($needle, $contents);
        }
    }
}
