<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountingEnginePhase12345IntegrationContractTest extends TestCase
{
    public function test_phase_1_to_5_posting_chain_is_wired_together(): void
    {
        $root = dirname(__DIR__, 2);

        $controller = file_get_contents($root . '/app/Http/Controllers/TransactionController.php');
        $engine = file_get_contents($root . '/app/AccountingEngine/AccountingEngine.php');
        $input = file_get_contents($root . '/app/AccountingEngine/DTO/TransactionInput.php');
        $posting = file_get_contents($root . '/app/Services/Accounting/TransactionPostingService.php');
        $postingStorage = file_get_contents($root . '/app/AccountingEngine/Services/PostingService.php');
        $mapping = file_get_contents($root . '/app/Services/Accounting/MappingResolverService.php');
        $preview = file_get_contents($root . '/app/AccountingEngine/Services/AccountingRulePreviewService.php');
        $register = file_get_contents($root . '/app/AccountingEngine/Services/PartyRegisterService.php');
        $opening = file_get_contents($root . '/app/Services/Setup/OpeningBalanceService.php');

        // Phase 1: HTTP layer uses the reusable engine/DTO boundary.
        $this->assertStringContainsString('AccountingEngineContract', $controller);
        $this->assertStringContainsString('TransactionInput::fromRequest', $controller);
        $this->assertStringContainsString('TransactionPostingService', $engine);
        $this->assertStringContainsString("'company_id'", $input);

        // Phase 2: company/date/rule-line metadata flows to journal lines.
        $this->assertStringContainsString("'company_id'", $posting);
        $this->assertStringContainsString("'company_id'", $postingStorage);
        $this->assertStringContainsString("'transaction_date'", $postingStorage);
        $this->assertStringContainsString("'rule_line_id'", $postingStorage);
        $this->assertStringContainsString("'amount_source'", $postingStorage);

        // Phase 3: V2 accounting rules are previewed before the legacy fallback.
        $this->assertStringContainsString('AccountingRulePreviewService', $mapping);
        $this->assertStringContainsString('$this->accountingRulePreviewService->preview', $mapping);
        $this->assertStringContainsString('$v2Preview !== null', $mapping);
        $this->assertStringContainsString('$rule = $this->resolve($transactionHeadId, $settlementTypeId);', $mapping);
        $this->assertStringContainsString('buildPreview', $preview);

        // Phase 4: hardened posting services are used atomically.
        $this->assertStringContainsString('FinancialPeriodGuard', $posting);
        $this->assertStringContainsString('JournalBuilder', $posting);
        $this->assertStringContainsString('JournalValidator', $posting);
        $this->assertStringContainsString('reserveWithLock', $posting);
        $this->assertStringContainsString('DB::transaction', $posting);

        // Phase 5: opening balance, due, and advance traceability are wired.
        $this->assertStringContainsString('recordOpeningBalance', $opening);
        $this->assertStringContainsString('source_voucher_detail_id', $register);
        $this->assertStringContainsString('Decrease Advance Asset', $register);
        $this->assertStringContainsString('Decrease Advance Liability', $register);
    }

    public function test_phase_1_to_5_required_source_files_exist(): void
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
            '/database/migrations/2026_05_23_000002_align_phase2_schema_data_safety.php',
            '/database/migrations/2026_05_23_000004_create_phase3_accounting_rule_engine_tables.php',
            '/database/migrations/2026_05_23_000005_harden_posting_and_register_traceability.php',
        ] as $path) {
            $this->assertFileExists($root . $path);
        }
    }
}
