<?php

namespace Tests\Feature\Accounting;

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\TimeZone;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Company\CompanyMasterDeletionService;
use App\Support\CompanyContext;
use App\Support\TransactionTypes;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanySetupModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HisebGhorDemoSeeder::class);
        $this->user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $this->company = Company::query()->findOrFail($this->user->company_id);
    }

    public function test_company_setup_and_missing_master_modules_are_available(): void
    {
        $this->actingAs($this->user)->get(route('company-setup.edit'))
            ->assertOk()
            ->assertSee('Company Setup')
            ->assertSee('Current Financial Year');

        $this->actingAs($this->user)->get(route('master.business-types.index'))->assertOk();
        $this->actingAs($this->user)->get(route('master.currencies.index'))->assertOk();
        $this->actingAs($this->user)->get(route('master.time-zones.index'))->assertOk();
        $this->actingAs($this->user)->get(route('master.financial-years.index'))->assertOk();

        $this->assertGreaterThan(0, BusinessType::query()->where('company_id', $this->company->id)->count());
        $this->assertGreaterThan(0, Currency::query()->where('company_id', $this->company->id)->count());
        $this->assertGreaterThan(0, TimeZone::query()->where('company_id', $this->company->id)->count());
        $this->assertGreaterThan(0, FinancialYear::query()->where('company_id', $this->company->id)->count());
    }

    public function test_company_setup_updates_usable_accounting_context(): void
    {
        $businessType = BusinessType::query()->where('company_id', $this->company->id)->where('code', 'SERVICE')->firstOrFail();
        $currency = Currency::query()->where('company_id', $this->company->id)->where('code', 'USD')->firstOrFail();
        $timeZone = TimeZone::query()->where('company_id', $this->company->id)->where('php_timezone', 'UTC')->firstOrFail();
        $financialYear = FinancialYear::query()->where('company_id', $this->company->id)->where('is_current', true)->firstOrFail();

        $this->actingAs($this->user)->put(route('company-setup.update'), [
            'name' => 'HisebGhor Services Limited',
            'short_name' => 'HG Services',
            'business_type_id' => $businessType->id,
            'trade_license_no' => 'TL-1001',
            'bin_vat_registration_no' => 'BIN-2002',
            'tin' => 'TIN-3003',
            'currency_id' => $currency->id,
            'accounting_method' => 'accrual',
            'time_zone_id' => $timeZone->id,
            'default_financial_year_id' => $financialYear->id,
            'default_branch' => 'Head Office',
            'address' => 'Dhaka, Bangladesh',
            'contact_email' => 'accounts@example.test',
            'contact_phone' => '01700000000',
            'website' => 'example.test',
            'status' => 'active',
        ])->assertRedirect(route('company-setup.edit'));

        $company = $this->company->fresh(['businessType', 'currency', 'timeZone', 'defaultFinancialYear']);
        $this->assertSame('HG Services', $company->short_name);
        $this->assertSame('SERVICE', $company->businessType->code);
        $this->assertSame('USD', $company->currency->code);
        $this->assertSame('UTC', $company->timeZone->php_timezone);
        $this->assertSame('https://example.test', $company->website);
        $this->assertTrue($company->isSetupComplete());

        $this->actingAs($this->user);
        $this->assertSame('$ 1,234.50', CompanyContext::money(1234.5));
    }

    public function test_transaction_posting_is_rejected_outside_an_open_financial_year(): void
    {
        $head = TransactionHead::query()
            ->where('company_id', $this->company->id)
            ->where('code', 'TH-SALE')
            ->firstOrFail();
        $moneyAccountId = \App\Models\MoneyAccount::query()
            ->where('company_id', $this->company->id)
            ->where('name', 'Main Cash Box')
            ->value('id');

        $response = $this->actingAs($this->user)->post(route('transactions.store'), [
            'category' => TransactionTypes::SALE,
            'settlement_type' => TransactionTypes::CASH,
            'transaction_date' => '1999-12-31',
            'transaction_head_id' => $head->id,
            'money_account_id' => $moneyAccountId,
            'party_id' => null,
            'amount' => '100.00',
            'reference' => 'OUTSIDE-FY',
            'description' => 'Should be rejected',
            'request_token' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasErrors('transaction_date');
        $this->assertDatabaseMissing('transactions', ['reference' => 'OUTSIDE-FY']);
    }

    public function test_safe_delete_of_selected_company_master_marks_setup_incomplete(): void
    {
        $currency = $this->company->currency()->firstOrFail();

        app(CompanyMasterDeletionService::class)->deleteCurrency($currency);

        $company = $this->company->fresh();
        $this->assertNull($company->currency_id);
        $this->assertNull($company->setup_completed_at);
        $this->assertFalse($company->isSetupComplete());

        $this->actingAs($this->user)->get(route('company-setup.edit'))->assertOk();
        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
        $this->assertNull($this->company->fresh()->currency_id);
    }
}
