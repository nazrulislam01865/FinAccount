<?php

namespace Tests\Unit;

use App\Models\AccountingRuleLine;
use App\Models\PartyLedgerMapping;
use PHPUnit\Framework\TestCase;

class PartySetupBackendContractTest extends TestCase
{
    public function test_party_mapping_schema_supports_template_style_receivable_and_payable_accounts(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/migrations/2026_06_12_000001_create_party_ledger_mappings_table.php');
        $model = file_get_contents($root . '/app/Models/PartyLedgerMapping.php');

        foreach ([
            'party_ledger_mappings',
            'mapping_purpose',
            'chart_of_account_id',
            'party_ledger_mapping_party_purpose_uq',
            'backfillExistingPartyLedgers',
        ] as $expected) {
            $this->assertStringContainsString($expected, $migration);
        }

        $this->assertContains(PartyLedgerMapping::PURPOSE_RECEIVABLE, PartyLedgerMapping::PURPOSES);
        $this->assertContains(PartyLedgerMapping::PURPOSE_PAYABLE, PartyLedgerMapping::PURPOSES);
        $this->assertStringContainsString('PURPOSE_CAPITAL', $model);
    }

    public function test_accounting_rules_can_resolve_each_party_mapping_purpose(): void
    {
        $root = dirname(__DIR__, 2);
        $resolver = file_get_contents($root . '/app/AccountingEngine/Services/LedgerResolver.php');
        $request = file_get_contents($root . '/app/Http/Requests/LedgerMappingRuleRequest.php');

        foreach ([
            'party_receivable',
            'party_payable',
            'party_advance_paid',
            'party_advance_received',
            'party_loan_payable',
            'party_salary_payable',
            'party_capital',
        ] as $source) {
            $this->assertContains($source, AccountingRuleLine::LEDGER_SOURCES);
            $this->assertStringContainsString("'{$source}'", $resolver);
            $this->assertStringContainsString($source, $request);
        }
    }

    public function test_party_setup_keeps_legacy_linked_ledger_compatibility(): void
    {
        $root = dirname(__DIR__, 2);
        $request = file_get_contents($root . '/app/Http/Requests/PartyRequest.php');
        $resolver = file_get_contents($root . '/app/AccountingEngine/Services/PartyLedgerResolver.php');
        $partyModel = file_get_contents($root . '/app/Models/Party.php');

        $this->assertStringContainsString("'linked_ledger_account_id' => \$primaryLedgerId", $request);
        $this->assertStringContainsString('legacyFallback', $resolver);
        $this->assertStringContainsString("static::saved", $partyModel);
        $this->assertStringContainsString('party_ledger_mappings', $partyModel);
    }

    public function test_party_delete_never_removes_posted_accounting_history(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/app/Services/Setup/EntityDeleteService.php');

        foreach ([
            "'voucher headers'",
            "'voucher lines'",
            "'journal headers'",
            "'journal lines'",
            "'opening balances'",
            "'due movements'",
            "'advance movements'",
            'Change the party status to Inactive instead',
        ] as $expected) {
            $this->assertStringContainsString($expected, $service);
        }

        $deleteMethodStart = strpos($service, 'private function deletePartyById');
        $deleteMethodEnd = strpos($service, 'private function referenceCount', $deleteMethodStart);
        $deleteMethod = substr($service, $deleteMethodStart, $deleteMethodEnd - $deleteMethodStart);

        $this->assertStringNotContainsString('deleteVoucherHeadersByIds', $deleteMethod);
        $this->assertStringNotContainsString("DB::table('voucher_headers')", $deleteMethod);
        $this->assertStringContainsString('->delete()', $deleteMethod);
    }

    public function test_party_form_exposes_separate_receivable_and_payable_capital_mappings(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/setup/parties.blade.php');

        $this->assertStringContainsString('name="receivable_ledger_account_id"', $view);
        $this->assertStringContainsString('name="payable_capital_ledger_account_id"', $view);
        $this->assertStringContainsString('mapping_purpose=receivable', $view);
        $this->assertStringContainsString('purpose-specific receivable/payable mappings', $view);
        $this->assertStringNotContainsString('name="linked_ledger_account_id"', $view);
    }

    public function test_sub_type_is_business_classification_only_and_never_drives_accounting(): void
    {
        $root = dirname(__DIR__, 2);
        $partyRequest = file_get_contents($root . '/app/Http/Requests/PartyRequest.php');
        $openingRequest = file_get_contents($root . '/app/Http/Requests/OpeningBalanceRequest.php');
        $view = file_get_contents($root . '/resources/views/setup/parties.blade.php');

        $this->assertStringContainsString('normalizeSubType', $partyRequest);
        $this->assertStringContainsString('Business classification only', $view);
        $this->assertStringNotContainsString("party->sub_type", $openingRequest);
        $this->assertStringContainsString('mappingPurposeForAccount', $openingRequest);
    }

    public function test_ledger_nature_is_automatic_and_supports_capital(): void
    {
        $root = dirname(__DIR__, 2);
        $profile = file_get_contents($root . '/app/Support/PartyAccountingProfile.php');
        $partyRequest = file_get_contents($root . '/app/Http/Requests/PartyRequest.php');
        $masterRequest = file_get_contents($root . '/app/Http/Requests/MasterPartyTypeRequest.php');
        $partyView = file_get_contents($root . '/resources/views/setup/parties.blade.php');
        $masterView = file_get_contents($root . '/resources/views/setup/master-data.blade.php');

        $this->assertStringContainsString("NATURE_CAPITAL = 'Capital'", $profile);
        $this->assertStringContainsString('PartyAccountingProfile::deriveNature', $partyRequest);
        $this->assertStringNotContainsString('blankToNull($this->default_ledger_nature)', $partyRequest);
        $this->assertStringContainsString('Rule::in(PartyAccountingProfile::NATURES)', $masterRequest);
        $this->assertStringContainsString('Primary Accounting Nature (Automatic)', $partyView);
        $this->assertStringContainsString('<option value="Capital">Capital / Owner Equity</option>', $masterView);
    }

    public function test_existing_party_natures_and_mappings_are_normalized_safely(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_06_12_000002_normalize_party_sub_type_and_ledger_nature.php'
        );

        foreach ([
            'normalizePartyTypeNatures',
            'normalizePartiesAndMappings',
            "'Capital' => 'capital'",
            'Data-normalization migration: intentionally non-destructive',
        ] as $expected) {
            $this->assertStringContainsString($expected, $migration);
        }
    }
}
