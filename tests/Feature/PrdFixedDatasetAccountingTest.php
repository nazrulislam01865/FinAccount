<?php

namespace Tests\Feature;

use App\AccountingReports\Services\AccountingReportService;
use Database\Seeders\PrdFixedDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrdFixedDatasetAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_prd_fixed_dataset_trial_balance_matches_expected_output(): void
    {
        $this->seed(PrdFixedDatasetSeeder::class);

        $report = app(AccountingReportService::class)->trialBalance([
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
            'include_zero_balances' => false,
        ]);

        $this->assertEquals(253000.00, round((float) $report['total_debit'], 2));
        $this->assertEquals(253000.00, round((float) $report['total_credit'], 2));
        $this->assertTrue($report['is_balanced']);
    }

    public function test_prd_fixed_dataset_income_statement_matches_expected_output(): void
    {
        $this->seed(PrdFixedDatasetSeeder::class);

        $report = app(AccountingReportService::class)->incomeStatement([
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
            'include_zero_balances' => false,
        ]);

        $this->assertEquals(15000.00, round((float) $report['revenue'], 2));
        $this->assertEquals(8000.00, round((float) $report['cost'], 2));
        $this->assertEquals(5000.00, round((float) $report['expense'], 2));
        $this->assertEquals(2000.00, round((float) $report['net_profit'], 2));
    }

    public function test_prd_fixed_dataset_balance_sheet_matches_expected_output(): void
    {
        $this->seed(PrdFixedDatasetSeeder::class);

        $report = app(AccountingReportService::class)->balanceSheet([
            'as_of_date' => '2026-12-31',
            'include_zero_balances' => false,
        ]);

        $this->assertEquals(240000.00, round((float) $report['assets'], 2));
        $this->assertEquals(18000.00, round((float) $report['liabilities'], 2));
        $this->assertEquals(220000.00, round((float) $report['equity'], 2));
        $this->assertEquals(2000.00, round((float) $report['retained_profit'], 2));
        $this->assertEquals(240000.00, round((float) $report['liabilities_and_equity'], 2));
        $this->assertTrue($report['is_balanced']);
    }
}
