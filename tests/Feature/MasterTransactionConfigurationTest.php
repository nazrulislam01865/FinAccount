<?php

namespace Tests\Feature;

use App\Models\AccountingOption;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\User;
use Database\Seeders\AccountingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterTransactionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_a_custom_transaction_category(): void
    {
        $user = $this->companyUser();

        $this->actingAs($user)
            ->post(route('master.store', 'transaction-categories'), [
                'value' => 'Refund',
                'label' => 'Refund',
                'money_label' => 'Refund Through',
                'voucher_prefix' => 'REF',
                'sort_order' => 40,
                'is_active' => 1,
            ])
            ->assertRedirect(route('master.index', 'transaction-categories'));

        $category = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', 'Refund')
            ->firstOrFail();

        $this->assertSame('Refund Through', $category->metadata['money_label']);
        $this->assertSame('REF', $category->metadata['voucher_prefix']);
        $this->assertTrue($category->is_active);
    }

    public function test_user_can_add_voucher_numbering_for_an_unconfigured_category(): void
    {
        $user = $this->companyUser();

        AccountingOption::query()->create([
            'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
            'value' => 'Refund',
            'label' => 'Refund',
            'sort_order' => 40,
            'metadata' => ['voucher_prefix' => 'REF', 'money_label' => 'Refund Through'],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('master.voucher-sequences.store'), [
                'category' => 'Refund',
                'prefix' => 'REF',
                'next_number' => 1,
                'padding' => 4,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.voucher-sequences.index'));

        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $user->company_id,
            'category' => 'Refund',
            'prefix' => 'REF',
            'next_number' => 1,
            'padding' => 4,
        ]);
    }

    public function test_duplicate_voucher_numbering_for_the_same_category_is_rejected(): void
    {
        $user = $this->companyUser();

        DocumentSequence::query()->create([
            'company_id' => $user->company_id,
            'category' => 'Sales',
            'prefix' => 'SAL',
            'next_number' => 1,
            'padding' => 4,
        ]);

        $this->actingAs($user)
            ->from(route('master.voucher-sequences.index'))
            ->post(route('master.voucher-sequences.store'), [
                'category' => 'Sales',
                'prefix' => 'SLS',
                'next_number' => 1,
                'padding' => 4,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.voucher-sequences.index'))
            ->assertSessionHasErrors('category');
    }

    public function test_core_transaction_category_cannot_be_renamed(): void
    {
        $user = $this->companyUser();
        $sales = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', 'Sales')
            ->firstOrFail();

        $this->actingAs($user)
            ->from(route('master.index', 'transaction-categories'))
            ->put(route('master.update', ['transaction-categories', $sales]), [
                'value' => 'Revenue',
                'label' => 'Sales',
                'money_label' => 'Receive In',
                'voucher_prefix' => 'SAL',
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('master.index', 'transaction-categories'))
            ->assertSessionHasErrors('value');

        $this->assertDatabaseHas('accounting_options', [
            'id' => $sales->id,
            'value' => 'Sales',
        ]);
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
