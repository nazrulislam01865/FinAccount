<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Support\TransactionTypes;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTemplateParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HisebGhorDemoSeeder::class);
        $this->user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
    }

    public function test_all_required_transaction_and_payment_types_are_seeded(): void
    {
        $this->assertSame(
            array_keys(TransactionTypes::definitions()),
            AccountingOption::query()
                ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('value')
                ->all(),
        );

        $this->assertSame(
            array_keys(TransactionTypes::settlementDefinitions()),
            AccountingOption::query()
                ->where('option_group', AccountingOption::GROUP_SETTLEMENT_TYPE)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('value')
                ->all(),
        );
    }

    public function test_each_transaction_type_has_only_the_required_automatic_rule_templates(): void
    {
        $companyId = (int) $this->user->company_id;
        $expectedCount = 0;

        foreach (TransactionTypes::definitions() as $type => $definition) {
            foreach ($definition['allowed_settlements'] as $settlement) {
                $expectedCount++;

                $rule = AccountingRule::query()
                    ->with('lines')
                    ->where('company_id', $companyId)
                    ->where('category', $type)
                    ->where('settlement_type', $settlement)
                    ->where('is_active', true)
                    ->sole();

                $expectedLineCount = $settlement === TransactionTypes::PARTIAL ? 3 : 2;
                $this->assertCount($expectedLineCount, $rule->lines);
            }
        }

        $this->assertSame(
            $expectedCount,
            AccountingRule::query()->where('company_id', $companyId)->where('is_active', true)->count(),
        );
    }

    public function test_transaction_heads_are_unified_and_are_not_bound_to_rules(): void
    {
        $heads = TransactionHead::query()
            ->where('company_id', $this->user->company_id)
            ->get();

        $this->assertNotEmpty($heads);
        $this->assertTrue($heads->every(fn (TransactionHead $head): bool => $head->accounting_rule_id === null));

        $saleHead = $heads->firstWhere('code', 'TH-SALE');
        $this->assertNotNull($saleHead);
        $this->assertSame(TransactionTypes::SALE, $saleHead->category);
        $this->assertSame(
            [TransactionTypes::CASH, TransactionTypes::CREDIT, TransactionTypes::PARTIAL],
            $saleHead->allowed_settlements,
        );
        $this->assertSame('Customer', $saleHead->party_type);
    }

    public function test_partial_sale_demo_transaction_is_balanced_without_a_selected_rule(): void
    {
        $transaction = Transaction::query()
            ->with('journalEntry.lines')
            ->where('company_id', $this->user->company_id)
            ->where('reference', 'INV-103')
            ->firstOrFail();

        $this->assertSame(TransactionTypes::SALE, $transaction->category);
        $this->assertSame(TransactionTypes::PARTIAL, $transaction->settlement_type);
        $this->assertSame('4000.00', number_format((float) $transaction->paid_amount, 2, '.', ''));
        $this->assertSame('6000.00', number_format((float) $transaction->due_amount, 2, '.', ''));

        $lines = $transaction->journalEntry->lines;
        $this->assertCount(3, $lines);
        $this->assertSame('10000.00', number_format((float) $lines->sum('debit'), 2, '.', ''));
        $this->assertSame('10000.00', number_format((float) $lines->sum('credit'), 2, '.', ''));
        $this->assertSame(1, $lines->where('money_account_id', '!=', null)->count());
        $this->assertSame(1, $lines->where('party_id', '!=', null)->count());
        $this->assertSame(3, JournalLine::query()->where('journal_entry_id', $transaction->journalEntry->id)->count());
    }

    public function test_non_accountant_transaction_entry_page_uses_business_wording(): void
    {
        $this->actingAs($this->user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('Transaction Head')
            ->assertSee('Amount')
            ->assertSee('Payment status and the journal are calculated automatically.')
            ->assertDontSee('How was payment handled?')
            ->assertDontSee('Select Accounting Rule');
    }
}
