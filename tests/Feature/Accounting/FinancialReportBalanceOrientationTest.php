<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Accounting\Reports\FinancialReportService;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportBalanceOrientationTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_sheet_uses_account_type_orientation_even_when_normal_balance_is_misconfigured(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();

        ChartOfAccount::query()
            ->where('company_id', $user->company_id)
            ->where('type', 'Income')
            ->update(['normal_balance' => 'Debit']);

        $report = app(FinancialReportService::class)->balanceSheet(
            (int) $user->company_id,
            ['as_of_date' => now()->toDateString()],
        );

        $this->assertGreaterThan(0, $report['retained_profit']);
        $this->assertEqualsWithDelta(0.0, $report['difference'], 0.01);
        $this->assertTrue($report['is_balanced']);
    }
}
