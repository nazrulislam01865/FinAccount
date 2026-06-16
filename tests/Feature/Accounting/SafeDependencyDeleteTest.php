<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingRule;
use App\Models\DocumentSequence;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeDependencyDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HisebGhorDemoSeeder::class);
        $this->user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
    }

    public function test_dependency_preview_does_not_delete_the_record(): void
    {
        $moneyAccount = MoneyAccount::query()->where('name', 'Main Cash Box')->firstOrFail();

        $this->actingAs($this->user)
            ->deleteJson(route('money-accounts.destroy', $moneyAccount), ['preview' => true])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('plan.has_dependencies', true);

        $this->assertDatabaseHas('money_accounts', ['id' => $moneyAccount->id]);
    }

    public function test_money_account_is_deleted_and_transactions_can_be_repaired_with_a_replacement(): void
    {
        $cash = MoneyAccount::query()->where('name', 'Main Cash Box')->firstOrFail();
        $bank = MoneyAccount::query()->where('name', 'BRAC Bank - Farm Account')->firstOrFail();
        $transaction = Transaction::query()
            ->where('money_account_id', $cash->id)
            ->where('category', 'Sales')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->deleteJson(route('money-accounts.destroy', $cash), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('money_accounts', ['id' => $cash->id]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'money_account_id' => null,
            'status' => 'incomplete',
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $transaction->id,
            'status' => 'incomplete',
        ]);

        $transaction->refresh();

        $this->actingAs($this->user)
            ->put(route('transactions.update', $transaction), [
                'category' => 'Sales',
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'transaction_head_id' => $transaction->transaction_head_id,
                'money_account_id' => $bank->id,
                'party_id' => null,
                'amount' => $transaction->amount,
                'reference' => $transaction->reference,
                'description' => $transaction->description,
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'money_account_id' => $bank->id,
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $transaction->id,
            'status' => 'posted',
        ]);
    }

    public function test_party_rule_and_head_deletions_clear_direct_relationships(): void
    {
        $party = Party::query()->where('code', 'S-001')->firstOrFail();
        $partyTransactionIds = Transaction::query()->where('party_id', $party->id)->pluck('id');

        $this->actingAs($this->user)
            ->deleteJson(route('parties.destroy', $party), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('parties', ['id' => $party->id]);
        foreach ($partyTransactionIds as $transactionId) {
            $this->assertDatabaseHas('transactions', [
                'id' => $transactionId,
                'party_id' => null,
                'status' => 'incomplete',
            ]);
        }

        $rule = AccountingRule::query()->where('code', 'R-SAL-01')->firstOrFail();
        $ruleHeadIds = TransactionHead::query()->where('accounting_rule_id', $rule->id)->pluck('id');

        $this->actingAs($this->user)
            ->deleteJson(route('accounting-rules.destroy', $rule), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('accounting_rules', ['id' => $rule->id]);
        foreach ($ruleHeadIds as $headId) {
            $this->assertDatabaseHas('transaction_heads', [
                'id' => $headId,
                'accounting_rule_id' => null,
                'is_active' => false,
            ]);
        }

        $head = TransactionHead::query()->where('code', 'TH-L-002')->firstOrFail();
        $headTransactionIds = Transaction::query()->where('transaction_head_id', $head->id)->pluck('id');

        $this->actingAs($this->user)
            ->deleteJson(route('transaction-heads.destroy', $head), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('transaction_heads', ['id' => $head->id]);
        foreach ($headTransactionIds as $transactionId) {
            $this->assertDatabaseHas('transactions', [
                'id' => $transactionId,
                'transaction_head_id' => null,
                'status' => 'incomplete',
            ]);
        }
    }

    public function test_voucher_numbering_can_be_permanently_deleted(): void
    {
        $sequence = DocumentSequence::query()
            ->where('company_id', $this->user->company_id)
            ->where('category', 'Payment')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->deleteJson(route('master.voucher-sequences.destroy', $sequence), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('document_sequences', ['id' => $sequence->id]);
    }
}
