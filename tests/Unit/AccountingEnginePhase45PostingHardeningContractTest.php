<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountingEnginePhase45PostingHardeningContractTest extends TestCase
{
    public function test_phase_4_posting_engine_hardening_files_exist_and_are_wired(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            '/app/AccountingEngine/Services/FinancialPeriodGuard.php',
            '/app/AccountingEngine/Services/JournalBuilder.php',
            '/app/AccountingEngine/Services/JournalValidator.php',
            '/app/AccountingEngine/Services/VoucherNumberService.php',
            '/app/AccountingEngine/Services/PostingService.php',
            '/app/AccountingEngine/Services/PartyRegisterService.php',
            '/app/AccountingEngine/Services/AuditTrailService.php',
        ] as $path) {
            $this->assertFileExists($root . $path);
        }

        $posting = file_get_contents($root . '/app/Services/Accounting/TransactionPostingService.php');

        $this->assertStringContainsString('FinancialPeriodGuard', $posting);
        $this->assertStringContainsString('JournalBuilder', $posting);
        $this->assertStringContainsString('JournalValidator', $posting);
        $this->assertStringContainsString('reserveWithLock', $posting);
        $this->assertStringContainsString('DB::transaction', $posting);
        $this->assertStringContainsString('recordIfNeeded', $posting);
        $this->assertStringContainsString('recordPostedVoucher', $posting);
    }

    public function test_phase_5_opening_balance_and_register_traceability_are_wired(): void
    {
        $root = dirname(__DIR__, 2);
        $opening = file_get_contents($root . '/app/Services/Setup/OpeningBalanceService.php');
        $register = file_get_contents($root . '/app/AccountingEngine/Services/PartyRegisterService.php');
        $migration = file_get_contents($root . '/database/migrations/2026_05_23_000005_harden_posting_and_register_traceability.php');

        $this->assertStringContainsString('Opening Voucher', $opening);
        $this->assertStringContainsString('reserveWithLock', $opening);
        $this->assertStringContainsString('assertLedgerIsPostable', $opening);
        $this->assertStringContainsString('recordOpeningBalance', $opening);
        $this->assertStringContainsString('Party is required', $opening);

        $this->assertStringContainsString('voucher_detail_id', $migration);
        $this->assertStringContainsString('source_voucher_detail_id', $migration);
        $this->assertStringContainsString('Decrease Advance Asset', $register);
        $this->assertStringContainsString('Decrease Advance Liability', $register);
        $this->assertStringContainsString('createDueMovementFromDetail', $register);
        $this->assertStringContainsString('createAdvanceMovementFromDetail', $register);
    }
}
