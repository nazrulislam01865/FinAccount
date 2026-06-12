<?php

namespace Tests\Unit;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionHeadConfigurationService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TransactionHeadScalableSetupTest extends TestCase
{
    public function test_active_balanced_rule_makes_head_ready(): void
    {
        $settlement = new SettlementType([
            'name' => 'Cash',
            'code' => 'CASH',
            'status' => 'Active',
            'sort_order' => 1,
        ]);
        $settlement->id = 1;

        $rule = new AccountingRule([
            'status' => 'Active',
            'party_required_mode' => 'Optional',
            'cash_bank_ledger_required' => true,
        ]);
        $rule->setRelation('settlementType', $settlement);
        $rule->setRelation('partyType', null);
        $rule->setRelation('lines', new Collection([
            new AccountingRuleLine([
                'side' => 'Debit',
                'ledger_source' => 'transaction_head',
            ]),
            new AccountingRuleLine([
                'side' => 'Credit',
                'ledger_source' => 'user_cash_bank',
            ]),
        ]));

        $head = new TransactionHead([
            'status' => 'Active',
            'category' => 'Expense',
            'default_primary_ledger_id' => 10,
            'is_user_selectable' => true,
        ]);
        $head->id = 5;
        $head->setRelation('defaultPrimaryLedger', null);
        $head->setRelation('accountingRules', new Collection([$rule]));
        $head->setRelation('ledgerMappingRules', new Collection());
        $head->setRelation('settlementTypes', new Collection());

        $profile = (new TransactionHeadConfigurationService())->summarize($head);

        self::assertTrue($profile['ready']);
        self::assertSame('Ready', $profile['setup_status']);
        self::assertSame('Optional', $profile['party_required_mode']);
        self::assertTrue($profile['cash_bank_required']);
        self::assertSame([1], $profile['settlement_type_ids']->all());
    }

    public function test_head_without_accounting_rule_is_not_ready_for_entry(): void
    {
        $head = new TransactionHead([
            'status' => 'Active',
            'category' => 'Expense',
            'default_primary_ledger_id' => 10,
            'is_user_selectable' => true,
        ]);
        $head->id = 6;
        $head->setRelation('defaultPrimaryLedger', null);
        $head->setRelation('accountingRules', new Collection());
        $head->setRelation('ledgerMappingRules', new Collection());
        $head->setRelation('settlementTypes', new Collection());

        $profile = (new TransactionHeadConfigurationService())->summarize($head);

        self::assertFalse($profile['ready']);
        self::assertSame('Accounting Rule Required', $profile['setup_status']);
    }

    public function test_transaction_head_form_contains_only_user_facing_fields(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/setup/transaction-heads.blade.php');

        foreach ([
            'name="name"',
            'name="category"',
            'name="default_primary_ledger_id"',
            'name="help_text"',
            'name="status"',
        ] as $requiredField) {
            self::assertStringContainsString($requiredField, $view);
        }

        foreach ([
            'name="default_movement"',
            'name="payment_method_required"',
            'name="party_required_mode"',
            'name="default_party_type_id"',
            'name="transaction_screen"',
            'name="linked_accounting_rule_code"',
            'name="developer_note"',
            'name="settlement_type_ids',
            'name="requires_party"',
            'name="requires_reference"',
        ] as $removedField) {
            self::assertStringNotContainsString($removedField, $view);
        }
    }
}
