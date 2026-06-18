<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingOption;
use App\Models\DocumentSequence;
use App\Models\User;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HisebGhorDemoSeeder::class);
        $this->user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
    }

    public function test_exact_accounting_menu_flow_is_visible(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Dashboard',
                'Transactions',
                'Transaction Entry',
                'Transaction Register',
                'Journal Entries',
                'Reports',
                'Account Balances',
                'Party Balances',
                'Income Statement',
                'Balance Sheet',
                'Cash Flow Statement',
                'Configuration',
                'Chart of Accounts',
                'Accounting Rules',
                'Transaction Heads',
                'Transaction Categories',
                'Voucher Numbering',
                'Party Types',
                'Parties',
                'Money Account Types',
                'Money Accounts',
                'Other Master Data',
            ]);
    }

    public function test_business_master_pages_are_available(): void
    {
        foreach (['party-types', 'money-account-types', 'transaction-categories'] as $section) {
            $this->actingAs($this->user)
                ->get(route('master.index', $section))
                ->assertOk();
        }

        $this->actingAs($this->user)
            ->get(route('master.voucher-sequences.index'))
            ->assertOk();

        $this->actingAs($this->user)
            ->get(route('master.overview'))
            ->assertOk()
            ->assertSee('Other Master Data');
    }

    public function test_new_party_type_is_available_to_party_and_rule_dropdowns(): void
    {
        $this->actingAs($this->user)
            ->post(route('master.store', 'party-types'), [
                'value' => 'Agent',
                'label' => 'Sales Agent',
                'sort_order' => 60,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.index', 'party-types'));

        $this->assertDatabaseHas('accounting_options', [
            'option_group' => AccountingOption::GROUP_PARTY_TYPE,
            'value' => 'Agent',
            'label' => 'Sales Agent',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('accounting_options', [
            'option_group' => AccountingOption::GROUP_RULE_PARTY_TYPE,
            'value' => 'Agent',
            'label' => 'Sales Agent',
            'is_active' => true,
        ]);
    }

    public function test_used_party_type_is_previewed_then_safely_deleted_and_dependents_are_deactivated(): void
    {
        $customer = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_PARTY_TYPE)
            ->where('value', 'Customer')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->deleteJson(route('master.destroy', ['party-types', $customer]), ['preview' => true])
            ->assertOk()
            ->assertJsonPath('plan.has_dependencies', true);

        $this->actingAs($this->user)
            ->deleteJson(route('master.destroy', ['party-types', $customer]), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('accounting_options', ['id' => $customer->id]);
        $this->assertDatabaseHas('parties', ['type' => null, 'is_active' => false]);
        $this->assertDatabaseHas('accounting_rules', ['party_type' => null, 'is_active' => false]);
    }

    public function test_transaction_category_internal_value_stays_locked(): void
    {
        $sales = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', 'Sales')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->put(route('master.update', ['transaction-categories', $sales]), [
                'label' => 'Sales & Revenue',
                'money_label' => 'Receive Through',
                'voucher_prefix' => 'SAL',
                'sort_order' => 15,
            ])
            ->assertRedirect(route('master.index', 'transaction-categories'));

        $sales->refresh();

        $this->assertSame('Sales', $sales->value);
        $this->assertSame('Sales & Revenue', $sales->label);
        $this->assertSame('Receive Through', $sales->metadata['money_label']);
        $this->assertSame(15, $sales->sort_order);
    }

    public function test_voucher_sequence_can_move_forward_safely(): void
    {
        $sequence = DocumentSequence::query()
            ->where('company_id', $this->user->company_id)
            ->where('category', 'Sales')
            ->firstOrFail();

        $newNextNumber = $sequence->next_number + 5;

        $this->actingAs($this->user)
            ->put(route('master.voucher-sequences.update', $sequence), [
                'category' => 'Sales',
                'prefix' => 'REV',
                'next_number' => $newNextNumber,
                'padding' => 5,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.voucher-sequences.index'));

        $sequence->refresh();

        $this->assertSame('REV', $sequence->prefix);
        $this->assertSame($newNextNumber, $sequence->next_number);
        $this->assertSame(5, $sequence->padding);
    }
}
