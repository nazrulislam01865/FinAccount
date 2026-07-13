<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\MoneyAccount;
use App\Models\OpeningBalance;
use App\Models\User;
use App\Services\Accounting\BasicStatementService;
use App\Services\Accounting\MoneyAccountService;
use App\Services\Dashboard\DashboardService;
use Database\Seeders\AccountingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyAccountSharedCoaTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_bank_accounts_can_share_the_same_asset_coa_without_duplicate_balances(): void
    {
        $this->seed(AccountingOptionSeeder::class);

        $company = Company::query()->create([
            'code' => 'BANK-'.uniqid(),
            'name' => 'Shared Bank COA Company',
            'currency_code' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $bankCoa = ChartOfAccount::query()->create([
            'company_id' => $company->id,
            'code' => '1120',
            'name' => 'Cash at Bank',
            'type' => 'Asset',
            'normal_balance' => 'Debit',
            'level' => 3,
            'is_active' => true,
        ]);

        foreach (['Krishi Bank', 'City Bank'] as $name) {
            $this->actingAs($user)
                ->post(route('money-accounts.store'), [
                    'name' => $name,
                    'kind' => 'Bank',
                    'chart_of_account_id' => $bankCoa->id,
                    'is_active' => 1,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('money-accounts.index'));
        }

        $accounts = MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        $this->assertCount(2, $accounts);
        $this->assertSame(1, $accounts->pluck('chart_of_account_id')->unique()->count());

        $cityBank = MoneyAccount::query()
            ->where('company_id', $company->id)
            ->where('name', 'City Bank')
            ->firstOrFail();
        $krishiBank = MoneyAccount::query()
            ->where('company_id', $company->id)
            ->where('name', 'Krishi Bank')
            ->firstOrFail();

        OpeningBalance::query()->create([
            'company_id' => $company->id,
            'balance_date' => now()->toDateString(),
            'chart_of_account_id' => $bankCoa->id,
            'money_account_id' => $cityBank->id,
            'debit' => 2500,
            'credit' => 0,
            'status' => OpeningBalance::STATUS_POSTED,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        OpeningBalance::query()->create([
            'company_id' => $company->id,
            'balance_date' => now()->toDateString(),
            'chart_of_account_id' => $bankCoa->id,
            'money_account_id' => $krishiBank->id,
            'debit' => 1000,
            'credit' => 0,
            'status' => OpeningBalance::STATUS_POSTED,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $pageData = app(MoneyAccountService::class)->pageData($company->id);
        $this->assertSame(2500.0, $pageData['balances'][$cityBank->id]);
        $this->assertSame(1000.0, $pageData['balances'][$krishiBank->id]);

        $statement = app(BasicStatementService::class)->summary($company->id);
        $this->assertSame(3500.0, $statement['cash']);

        $dashboard = app(DashboardService::class)->summary($company->id);
        $this->assertSame(3500.0, $dashboard['metrics']['available_money']);
        $this->assertSame(
            ['City Bank' => 2500.0, 'Krishi Bank' => 1000.0],
            $dashboard['moneyAccounts']->pluck('balance', 'name')->all(),
        );
    }
}
