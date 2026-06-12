<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CashBankSetupBackendContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_cash_bank_id_is_generated_by_backend_and_is_not_editable(): void
    {
        $request = file_get_contents($this->root . '/app/Http/Requests/CashBankAccountRequest.php');
        $service = file_get_contents($this->root . '/app/Services/Setup/CashBankAccountService.php');
        $model = file_get_contents($this->root . '/app/Models/CashBankAccount.php');
        $view = file_get_contents($this->root . '/resources/views/setup/cash-bank-accounts.blade.php');

        $this->assertStringContainsString("request->remove('cash_bank_code')", $request);
        $this->assertStringContainsString('nextCashBankCode', $service);
        $this->assertStringContainsString("str_pad((string) \$next, 5", $service);
        $this->assertStringContainsString('Cash/Bank ID is immutable', $model);
        $this->assertStringContainsString('Cash/Bank ID (Automatic)', $view);
        $this->assertStringContainsString('readonly', $view);
        $this->assertStringNotContainsString('name="cash_bank_code"', $view);
    }

    public function test_company_scoping_and_company_specific_uniqueness_are_present(): void
    {
        $request = file_get_contents($this->root . '/app/Http/Requests/CashBankAccountRequest.php');
        $controller = file_get_contents($this->root . '/app/Http/Controllers/Setup/CashBankAccountController.php');
        $dropdown = file_get_contents($this->root . '/app/Http/Controllers/Api/DropdownController.php');
        $migration = file_get_contents($this->root . '/database/migrations/2026_06_12_000003_harden_cash_bank_account_setup.php');

        $this->assertStringContainsString("where('company_id', \$companyId)", $request);
        $this->assertStringContainsString('ensureAccountBelongsToCurrentCompany', $controller);
        $this->assertStringContainsString("where('company_id', \$companyId)", $dropdown);
        $this->assertStringContainsString('cash_bank_company_code_unique', $migration);
        $this->assertStringContainsString('cash_bank_company_name_unique', $migration);
    }

    public function test_type_specific_ledgers_and_mobile_wallet_are_supported(): void
    {
        $request = file_get_contents($this->root . '/app/Http/Requests/CashBankAccountRequest.php');
        $dropdown = file_get_contents($this->root . '/app/Http/Controllers/Api/DropdownController.php');
        $coaRequest = file_get_contents($this->root . '/app/Http/Requests/ChartOfAccountRequest.php');
        $migration = file_get_contents($this->root . '/database/migrations/2026_06_12_000003_harden_cash_bank_account_setup.php');

        $this->assertStringContainsString("ledger_type !== 'Cash'", $request);
        $this->assertStringContainsString("ledger_type !== 'Bank'", $request);
        $this->assertStringContainsString("ledger_type !== 'Mobile Wallet'", $request);
        $this->assertStringContainsString("where('ledger_type', 'Mobile Wallet')", $dropdown);
        $this->assertStringContainsString("['Cash', 'Bank', 'Mobile Wallet']", $coaRequest);
        $this->assertStringContainsString('MOBILE_WALLET', $migration);
    }

    public function test_accounting_history_protects_mapping_and_deletion(): void
    {
        $request = file_get_contents($this->root . '/app/Http/Requests/CashBankAccountRequest.php');
        $deleteService = file_get_contents($this->root . '/app/Services/Setup/EntityDeleteService.php');

        $this->assertStringContainsString('hasAccountingHistory', $request);
        $this->assertStringContainsString('Linked Ledger Account cannot be changed', $request);
        $this->assertStringContainsString('Type cannot be changed', $request);
        $this->assertStringContainsString('Change the account status to Inactive instead', $deleteService);
        $this->assertStringContainsString("'journal lines'", $deleteService);
        $this->assertStringContainsString("'opening balances'", $deleteService);
    }

    public function test_cash_bank_reports_ignore_deleted_and_cross_company_setup_rows(): void
    {
        $report = file_get_contents($this->root . '/app/AccountingReports/Services/AccountingReportService.php');

        $this->assertStringContainsString("whereNull('cb.deleted_at')", $report);
        $this->assertStringContainsString("where('cb.company_id', '=', \$companyId)", $report);
    }

    public function test_unrelated_edits_do_not_reset_opening_balance(): void
    {
        $request = file_get_contents($this->root . '/app/Http/Requests/CashBankAccountRequest.php');
        $service = file_get_contents($this->root . '/app/Services/Setup/CashBankAccountService.php');

        $this->assertStringContainsString("if (\$this->exists('opening_balance'))", $request);
        $this->assertStringContainsString("array_key_exists('opening_balance', \$data)", $service);
        $this->assertStringContainsString("\$account?->opening_balance ?? 0", $service);
    }
}
