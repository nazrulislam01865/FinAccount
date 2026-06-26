<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingOptionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_alone_installs_every_required_template_option(): void
    {
        $expectedCounts = [
            AccountingOption::GROUP_ACCOUNT_TYPE => 5,
            AccountingOption::GROUP_NORMAL_BALANCE => 2,
            AccountingOption::GROUP_MONEY_ACCOUNT_KIND => 3,
            AccountingOption::GROUP_PARTY_TYPE => 5,
            AccountingOption::GROUP_RULE_PARTY_TYPE => 6,
            AccountingOption::GROUP_TRANSACTION_CATEGORY => 11,
            AccountingOption::GROUP_SETTLEMENT_TYPE => 3,
            AccountingOption::GROUP_ACCOUNTING_SOURCE => 4,
        ];

        foreach ($expectedCounts as $group => $count) {
            $this->assertSame($count, AccountingOption::query()
                ->where('option_group', $group)
                ->where('is_active', true)
                ->count());
        }

        $this->assertSame(41, AccountingOption::query()->count());
    }
}
