<?php

namespace Tests\Feature;

use App\Models\CashBankAccount;
use App\Models\Party;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\User;
use Database\Seeders\HisebGhorQaDatasetSeeder;
use Tests\TestCase;

class HisebGhorGoldenAccountingFlowTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! preg_match('/(qa|test)/i', $database)) {
            $this->markTestSkipped("Refusing to refresh non-QA MySQL database: {$database}");
        }

        $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        $this->seed(HisebGhorQaDatasetSeeder::class);
        $this->user = User::query()->where('email', env('ADMIN_EMAIL', 'admin@example.com'))->firstOrFail();
    }

    public function test_golden_transaction_flow_and_reports(): void
    {
        $this->actingAs($this->user);

        $transactions = $this->goldenTransactions();

        foreach ($transactions as $tx) {
            $payload = $this->payloadFor($tx);

            $preview = $this->postJson('/api/transactions/preview', $payload)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->json('data');

            $this->assertTrue($preview['balanced'], $tx['case_id'] . ' preview must be balanced.');
            $this->assertSame(round($tx['amount'], 2), round((float) $preview['total_debit'], 2));
            $this->assertSame(round($tx['amount'], 2), round((float) $preview['total_credit'], 2));
            $this->assertContains($tx['expected_debit_account'], collect($preview['entries'])->where('debit', '>', 0)->pluck('account_name')->all());
            $this->assertContains($tx['expected_credit_account'], collect($preview['entries'])->where('credit', '>', 0)->pluck('account_name')->all());

            $this->postJson('/api/transactions', $payload)
                ->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.total_debit', (float) $tx['amount'])
                ->assertJsonPath('data.total_credit', (float) $tx['amount']);
        }

        $trialBalance = $this->getJson('/api/reports/trial-balance?from_date=2026-01-01&to_date=2026-12-31&include_zero_balances=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertTrue($trialBalance['is_balanced']);
        $this->assertEquals(243000.00, round((float) $trialBalance['total_debit'], 2));
        $this->assertEquals(243000.00, round((float) $trialBalance['total_credit'], 2));
        $this->assertEquals(0.00, round((float) $trialBalance['difference'], 2));

        $profitLoss = $this->getJson('/api/reports/profit-loss?from_date=2026-01-01&to_date=2026-12-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertEquals(42000.00, round((float) $profitLoss['revenue'], 2));
        $this->assertEquals(65000.00, round((float) $profitLoss['expense'], 2));
        $this->assertEquals(-23000.00, round((float) $profitLoss['net_profit'], 2));

        $balanceSheet = $this->getJson('/api/reports/balance-sheet?as_of_date=2026-12-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertTrue($balanceSheet['is_balanced']);
        $this->assertEquals(178000.00, round((float) $balanceSheet['assets'], 2));
        $this->assertEquals(46000.00, round((float) $balanceSheet['liabilities'], 2));
        $this->assertEquals(155000.00, round((float) $balanceSheet['equity'], 2));
        $this->assertEquals(-23000.00, round((float) $balanceSheet['retained_profit'], 2));
        $this->assertEquals(178000.00, round((float) $balanceSheet['liabilities_and_equity'], 2));
    }

    public function test_validation_blocks_invalid_transaction(): void
    {
        $this->actingAs($this->user);

        $payload = $this->payloadFor($this->goldenTransactions()[0]);
        unset($payload['cash_bank_account_id']);

        $this->postJson('/api/transactions/preview', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cash_bank_account_id']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function goldenTransactions(): array
    {
        return [
            ['case_id' => 'TX-001', 'date' => '2026-06-01', 'head' => 'Fuel Expense', 'settlement' => 'CASH', 'party' => 'Green Seed Supplier Ltd.', 'cash_bank' => 'Office Cash', 'amount' => 5000.00, 'expected_debit_account' => 'Fuel Expense', 'expected_credit_account' => 'Cash in Hand'],
            ['case_id' => 'TX-002', 'date' => '2026-06-02', 'head' => 'Vehicle Rent Income', 'settlement' => 'BANK', 'party' => 'Karim Agro Farm', 'cash_bank' => 'BRAC Bank', 'amount' => 12000.00, 'expected_debit_account' => 'BRAC Bank Current Account', 'expected_credit_account' => 'Vehicle Rent Income'],
            ['case_id' => 'TX-003', 'date' => '2026-06-03', 'head' => 'Vehicle Rent Income', 'settlement' => 'DUE', 'party' => 'Karim Agro Farm', 'cash_bank' => null, 'amount' => 30000.00, 'expected_debit_account' => 'Accounts Receivable', 'expected_credit_account' => 'Vehicle Rent Income'],
            ['case_id' => 'TX-004', 'date' => '2026-06-04', 'head' => 'Customer Payment Received', 'settlement' => 'BANK', 'party' => 'Karim Agro Farm', 'cash_bank' => 'BRAC Bank', 'amount' => 10000.00, 'expected_debit_account' => 'BRAC Bank Current Account', 'expected_credit_account' => 'Accounts Receivable'],
            ['case_id' => 'TX-005', 'date' => '2026-06-05', 'head' => 'Fuel Expense', 'settlement' => 'DUE', 'party' => 'Green Seed Supplier Ltd.', 'cash_bank' => null, 'amount' => 40000.00, 'expected_debit_account' => 'Fuel Expense', 'expected_credit_account' => 'Accounts Payable'],
            ['case_id' => 'TX-006', 'date' => '2026-06-06', 'head' => 'Supplier Payment', 'settlement' => 'BANK', 'party' => 'Green Seed Supplier Ltd.', 'cash_bank' => 'BRAC Bank', 'amount' => 15000.00, 'expected_debit_account' => 'Accounts Payable', 'expected_credit_account' => 'BRAC Bank Current Account'],
            ['case_id' => 'TX-007', 'date' => '2026-06-07', 'head' => 'Salary Due Entry', 'settlement' => 'DUE', 'party' => 'QA Employee Rahim', 'cash_bank' => null, 'amount' => 20000.00, 'expected_debit_account' => 'Salary Expense', 'expected_credit_account' => 'Salary Payable'],
            ['case_id' => 'TX-008', 'date' => '2026-06-08', 'head' => 'Salary Due Payment', 'settlement' => 'BANK', 'party' => 'QA Employee Rahim', 'cash_bank' => 'BRAC Bank', 'amount' => 20000.00, 'expected_debit_account' => 'Salary Payable', 'expected_credit_account' => 'BRAC Bank Current Account'],
            ['case_id' => 'TX-009', 'date' => '2026-06-09', 'head' => 'Advance Paid', 'settlement' => 'BANK', 'party' => 'Green Seed Supplier Ltd.', 'cash_bank' => 'BRAC Bank', 'amount' => 7000.00, 'expected_debit_account' => 'Advance to Supplier / Employee', 'expected_credit_account' => 'BRAC Bank Current Account'],
            ['case_id' => 'TX-010', 'date' => '2026-06-10', 'head' => 'Advance Received', 'settlement' => 'CASH', 'party' => 'Karim Agro Farm', 'cash_bank' => 'Office Cash', 'amount' => 6000.00, 'expected_debit_account' => 'Cash in Hand', 'expected_credit_account' => 'Advance from Customer'],
        ];
    }

    /**
     * @param array<string, mixed> $tx
     * @return array<string, mixed>
     */
    private function payloadFor(array $tx): array
    {
        $payload = [
            'voucher_date' => $tx['date'],
            'transaction_head_id' => TransactionHead::query()->where('name', $tx['head'])->value('id'),
            'settlement_type_id' => SettlementType::query()->where('code', $tx['settlement'])->value('id'),
            'amount' => $tx['amount'],
            'reference' => $tx['case_id'],
            'reference_no' => $tx['case_id'],
            'notes' => 'Automated Laravel feature test ' . $tx['case_id'],
            'narration' => 'Automated Laravel feature test ' . $tx['case_id'],
            'status' => 'Posted',
        ];

        if (! empty($tx['party'])) {
            $payload['party_id'] = Party::query()->where('party_name', $tx['party'])->value('id');
        }

        if (! empty($tx['cash_bank'])) {
            $payload['cash_bank_account_id'] = CashBankAccount::query()->where('cash_bank_name', $tx['cash_bank'])->value('id');
        }

        return $payload;
    }
}
