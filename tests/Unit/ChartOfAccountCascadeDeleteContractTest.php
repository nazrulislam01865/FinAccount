<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ChartOfAccountCascadeDeleteContractTest extends TestCase
{
    public function test_all_coa_views_expose_delete_controls_and_keep_the_current_tab(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/resources/views/setup/chart-of-accounts.blade.php');

        $this->assertStringContainsString("value=\"tree\"", $view);
        $this->assertStringContainsString("value=\"posting\"", $view);
        $this->assertStringContainsString("value=\"full\"", $view);
        $this->assertStringContainsString("'Delete Branch'", $view);
        $this->assertGreaterThanOrEqual(6, substr_count($view, 'data-coa-delete-form'));
        $this->assertStringContainsString('showCoaReassignmentNotice', $view);
    }

    public function test_cascade_delete_preserves_rules_and_clears_only_ledger_assignments(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Services/Setup/EntityDeleteService.php');
        $controller = file_get_contents($root . '/app/Http/Controllers/Setup/ChartOfAccountController.php');

        $this->assertStringContainsString('chartOfAccountSubtree', $service);
        $this->assertStringContainsString('clearAccountingRuleReferences', $service);
        $this->assertStringContainsString('clearLegacyRuleReferences', $service);
        $this->assertStringContainsString("'ledger_id' => null", $service);
        $this->assertStringContainsString("'status' => 'Inactive'", $service);
        $this->assertStringNotContainsString('softDeleteAffectedRules', $service);
        $this->assertStringContainsString('cleared_rule_count', $controller);
        $this->assertStringContainsString('reassignment_message', $controller);
        $this->assertStringNotContainsString("'label' => 'Protected system ledgers'", $service);
    }

    public function test_every_affected_setup_page_shows_a_reassignment_warning(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'resources/views/setup/accounting-rules-setup.blade.php',
            'resources/views/setup/cash-bank-accounts.blade.php',
            'resources/views/setup/parties.blade.php',
            'resources/views/setup/transaction-heads.blade.php',
            'resources/views/setup/master-data.blade.php',
        ] as $viewPath) {
            $view = file_get_contents($root . '/' . $viewPath);
            $this->assertStringContainsString('reassignment required', strtolower($view));
        }
    }

    public function test_historical_accounting_rows_are_preserved_without_blocking_coa_deletion(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Services/Setup/EntityDeleteService.php');

        foreach ([
            'opening_balances',
            'voucher_details',
            'journal_lines',
            'due_register',
            'advance_register',
        ] as $table) {
            $this->assertStringContainsString("['{$table}'", $service);
        }

        $this->assertStringContainsString('chartOfAccountHistoryImpact', $service);
        $this->assertStringContainsString("'can_delete' => true", $service);
        $this->assertStringContainsString('historical rows will NOT be deleted or blanked', $service);
        $this->assertStringNotContainsString('cannot be deleted because it contains accounting history', $service);

        foreach ([
            'app/Models/OpeningBalance.php',
            'app/Models/VoucherDetail.php',
            'app/Models/JournalLine.php',
            'app/Models/DueRegister.php',
            'app/Models/AdvanceRegister.php',
        ] as $modelPath) {
            $model = file_get_contents($root . '/' . $modelPath);
            $this->assertStringContainsString('->withTrashed()', $model);
        }
    }

    public function test_configuration_links_are_nullable_for_safe_reassignment(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_10_000001_make_coa_configuration_links_nullable.php');

        foreach ([
            "'cash_bank_accounts', 'linked_ledger_account_id'",
            "'parties', 'linked_ledger_account_id'",
            "'ledger_mapping_rules', 'debit_account_id'",
            "'ledger_mapping_rules', 'credit_account_id'",
            'nullOnDelete',
        ] as $expected) {
            $this->assertStringContainsString($expected, $migration);
        }
    }
}
