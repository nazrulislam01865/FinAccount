<?php

namespace Tests\Feature;

use App\Models\AccountingOption;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\User;
use App\Support\TransactionTypes;
use Database\Seeders\AccountingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterTransactionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_types_are_protected_system_values(): void
    {
        $user = $this->companyUser();

        $this->actingAs($user)
            ->from(route('master.index', 'transaction-categories'))
            ->post(route('master.store', 'transaction-categories'), [
                'value' => 'REFUND',
                'label' => 'Refund',
                'money_label' => 'Refund Through',
                'voucher_prefix' => 'REF',
                'sort_order' => 120,
                'is_active' => 1,
            ])
            ->assertRedirect(route('master.index', 'transaction-categories'))
            ->assertSessionHasErrors('master_data');

        $this->assertDatabaseMissing('accounting_options', [
            'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
            'value' => 'REFUND',
        ]);
    }

    public function test_voucher_numbering_can_be_added_for_a_system_transaction_type(): void
    {
        $user = $this->companyUser();

        $this->actingAs($user)
            ->post(route('master.voucher-sequences.store'), [
                'category' => TransactionTypes::SALE,
                'prefix' => 'SAL',
                'next_number' => 1,
                'padding' => 4,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.voucher-sequences.index'));

        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $user->company_id,
            'category' => TransactionTypes::SALE,
            'prefix' => 'SAL',
        ]);
    }

    public function test_duplicate_voucher_numbering_is_rejected(): void
    {
        $user = $this->companyUser();
        DocumentSequence::query()->create([
            'company_id' => $user->company_id,
            'category' => TransactionTypes::SALE,
            'prefix' => 'SAL',
            'next_number' => 1,
            'padding' => 4,
        ]);

        $this->actingAs($user)
            ->from(route('master.voucher-sequences.index'))
            ->post(route('master.voucher-sequences.store'), [
                'category' => TransactionTypes::SALE,
                'prefix' => 'SLS',
                'next_number' => 1,
                'padding' => 4,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.voucher-sequences.index'))
            ->assertSessionHasErrors('category');
    }

    public function test_system_transaction_type_code_cannot_be_renamed(): void
    {
        $user = $this->companyUser();
        $sale = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', TransactionTypes::SALE)
            ->firstOrFail();

        $this->actingAs($user)
            ->from(route('master.index', 'transaction-categories'))
            ->put(route('master.update', ['transaction-categories', $sale]), [
                'value' => 'REVENUE',
                'label' => 'Sale',
                'money_label' => 'Received In',
                'voucher_prefix' => 'SAL',
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('master.index', 'transaction-categories'))
            ->assertSessionHasErrors('value');

        $this->assertDatabaseHas('accounting_options', ['id' => $sale->id, 'value' => TransactionTypes::SALE]);
    }

    private function companyUser(): User
    {
        $this->seed(AccountingOptionSeeder::class);
        $company = Company::query()->create([
            'code' => 'TEST-'.uniqid(), 'name' => 'Test Company', 'currency_code' => 'BDT', 'timezone' => 'Asia/Dhaka', 'status' => 'active',
        ]);

        return User::factory()->create(['company_id' => $company->id]);
    }
}
