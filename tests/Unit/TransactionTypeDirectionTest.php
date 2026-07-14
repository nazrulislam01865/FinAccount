<?php

namespace Tests\Unit;

use App\Support\TransactionTypes;
use PHPUnit\Framework\TestCase;

class TransactionTypeDirectionTest extends TestCase
{
    public function test_transaction_direction_dropdown_has_the_required_four_options(): void
    {
        $this->assertSame([
            TransactionTypes::FLOW_INCOMING => 'Money In',
            TransactionTypes::FLOW_OUTGOING => 'Money Out',
            TransactionTypes::FLOW_TRANSFER => 'Transfer',
            TransactionTypes::FLOW_NON_CASH => 'Non-Cash',
        ], TransactionTypes::flowLabels());
    }

    public function test_custom_transfer_and_non_cash_directions_are_preserved(): void
    {
        $this->assertSame(
            TransactionTypes::FLOW_TRANSFER,
            TransactionTypes::flow('CUSTOM_TRANSFER', ['flow' => TransactionTypes::FLOW_TRANSFER]),
        );

        $this->assertSame(
            TransactionTypes::FLOW_NON_CASH,
            TransactionTypes::flow('JOURNAL_ADJUSTMENT', ['flow' => TransactionTypes::FLOW_NON_CASH]),
        );
    }
}
