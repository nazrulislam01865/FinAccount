<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\MoneyAccount;
use App\Models\User;
use Database\Seeders\AccountingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingSetupPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_company_user_can_open_every_remaining_template_page(): void
    {
        $user = $this->companyUser();
        $this->actingAs($user);

        $pages = [
            'money-accounts.index' => 'Money Accounts',
            'parties.index' => 'Parties',
            'accounting-rules.index' => 'Accounting Rules',
            'transaction-heads.index' => 'Transaction Heads',
            'journal-entries.index' => 'Journal Entries',
            'balances.index' => 'Balances',
            'basic-statements.index' => 'Basic Statements View',
        ];

        foreach ($pages as $route => $text) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee($text);
        }
    }

    public function test_money_account_crud_is_company_scoped_and_uses_an_asset_coa(): void
    {
        $user = $this->companyUser();
        $account = ChartOfAccount::query()->create([
            'company_id' => $user->company_id,
            'code' => '1119',
            'name' => 'Test Bank',
            'type' => 'Asset',
            'normal_balance' => 'Debit',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('money-accounts.store'), [
                'name' => 'Testing Account',
                'kind' => 'Bank',
                'chart_of_account_id' => $account->id,
                'opening_balance' => 1250,
                'is_active' => 1,
            ])
            ->assertRedirect(route('money-accounts.index'));

        $moneyAccount = MoneyAccount::query()->where('company_id', $user->company_id)->firstOrFail();
        $this->assertSame('Testing Account', $moneyAccount->name);
        $this->assertSame($account->id, $moneyAccount->chart_of_account_id);
    }

    private function companyUser(): User
    {
        $this->seed(AccountingOptionSeeder::class);
        $company = Company::query()->create([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Company',
            'currency_code' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ]);

        return User::factory()->create(['company_id' => $company->id]);
    }
}
