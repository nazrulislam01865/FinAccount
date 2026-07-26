<?php

namespace Tests\Feature\Accounting;

use App\Models\FinancialYear;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Support\TransactionTypes;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackdatedTransactionEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_backdated_entry_can_post_inside_another_active_open_financial_year(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $previousYear = (int) now()->subYear()->format('Y');
        $backdatedDate = $previousYear.'-06-15';

        FinancialYear::query()->create([
            'company_id' => $user->company_id,
            'name' => 'FY '.$previousYear.' Backdated Test',
            'start_date' => $previousYear.'-01-01',
            'end_date' => $previousYear.'-12-31',
            'is_active' => true,
            'is_current' => false,
            'status' => FinancialYear::STATUS_OPEN,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('Backdated Entry')
            ->assertSee('aria-readonly="true"', false);

        $this->actingAs($user)
            ->get(route('transactions.create', ['backdated' => 1]))
            ->assertOk()
            ->assertSee('Backdated Entry')
            ->assertSee($previousYear.'-01-01')
            ->assertSee('aria-readonly="false"', false)
            ->assertSee('data-backdated-max="'.now()->toDateString().'"', false);

        $head = TransactionHead::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'TH-SALE')
            ->firstOrFail();
        $moneyAccount = MoneyAccount::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Main Cash Box')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'category' => TransactionTypes::SALE,
            'settlement_type' => TransactionTypes::CASH,
            'transaction_date' => $backdatedDate,
            'is_backdated' => 1,
            'transaction_head_id' => $head->id,
            'money_account_id' => $moneyAccount->id,
            'amount' => '1250.00',
            'reference' => 'BACKDATED-ALLOWED',
            'request_token' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('transactions.index'));
        $this->assertDatabaseHas('transactions', [
            'company_id' => $user->company_id,
            'reference' => 'BACKDATED-ALLOWED',
            'transaction_date' => $backdatedDate,
            'status' => 'posted',
        ]);
    }

    public function test_past_date_requires_backdated_entry_to_be_enabled(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $previousYear = (int) now()->subYear()->format('Y');

        FinancialYear::query()->create([
            'company_id' => $user->company_id,
            'name' => 'FY '.$previousYear.' Validation Test',
            'start_date' => $previousYear.'-01-01',
            'end_date' => $previousYear.'-12-31',
            'is_active' => true,
            'is_current' => false,
            'status' => FinancialYear::STATUS_OPEN,
        ]);

        $head = TransactionHead::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'TH-SALE')
            ->firstOrFail();
        $moneyAccount = MoneyAccount::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Main Cash Box')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'category' => TransactionTypes::SALE,
            'settlement_type' => TransactionTypes::CASH,
            'transaction_date' => $previousYear.'-07-01',
            'is_backdated' => 0,
            'transaction_head_id' => $head->id,
            'money_account_id' => $moneyAccount->id,
            'amount' => '500.00',
            'reference' => 'BACKDATED-NOT-ENABLED',
            'request_token' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasErrors('transaction_date');
        $this->assertDatabaseMissing('transactions', ['reference' => 'BACKDATED-NOT-ENABLED']);
    }
}
