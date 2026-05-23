<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountingEnginePhase2SchemaContractTest extends TestCase
{
    public function test_phase_2_schema_alignment_migration_contains_required_columns(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_05_23_000002_align_phase2_schema_data_safety.php');

        foreach ([
            'lock_date',
            'is_current',
            'category',
            'default_primary_ledger_id',
            'default_movement',
            'payment_method_required',
            'party_required_mode',
            'transaction_screen',
            'submitted_at',
            'submitted_by',
            'approved_at',
            'approved_by',
            'posted_by',
            'voided_at',
            'voided_by',
            'void_reason',
            'company_id',
            'branch_id',
            'transaction_date',
            'rule_line_id',
            'amount_source',
        ] as $column) {
            $this->assertStringContainsString($column, $migration);
        }
    }

    public function test_phase_2_reusable_posting_period_guard_exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/app/Services/Accounting/PostingPeriodGuard.php');
        $this->assertStringContainsString(
            'ensureOpenForDate',
            file_get_contents(dirname(__DIR__, 2) . '/app/Services/Accounting/PostingPeriodGuard.php')
        );
    }
}
