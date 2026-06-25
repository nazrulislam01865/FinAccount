<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingRule;
use App\Models\DocumentSequence;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Support\TransactionTypes;
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

    public function test_money_account_is_deleted_and_transaction_can_be_repaired_with_a_replacement(): void
    {
        $cash = MoneyAccount::query()->where('name', 'Main Cash Box')->firstOrFail();
        $bank = MoneyAccount::query()->where('name', 'BRAC Bank - Farm Account')->firstOrFail();
        $transaction = Transaction::query()
            ->where('money_account_id', $cash->id)
            ->where('category', TransactionTypes::SALE)
            ->where('settlement_type', TransactionTypes::CASH)
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

        $transaction->refresh();

        $this->actingAs($this->user)
            ->put(route('transactions.update', $transaction), [
                'category' => TransactionTypes::SALE,
                'settlement_type' => TransactionTypes::CASH,
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
    }

    public function test_party_rule_and_head_deletions_do_not_reintroduce_rule_bound_heads(): void
    {
        $party = Party::query()->where('code', 'S-001')->firstOrFail();
        $partyTransactionIds = Transaction::query()->where('party_id', $party->id)->pluck('id');

        $this->actingAs($this->user)
            ->deleteJson(route('parties.destroy', $party), ['confirmed' => true])
            ->assertOk();

        foreach ($partyTransactionIds as $transactionId) {
            $this->assertDatabaseHas('transactions', [
                'id' => $transactionId,
                'party_id' => null,
                'status' => 'incomplete',
            ]);
        }

        $rule = AccountingRule::query()
            ->where('category', TransactionTypes::SALE)
            ->where('settlement_type', TransactionTypes::CASH)
            ->firstOrFail();
        $activeHeadCount = TransactionHead::query()->where('is_active', true)->count();

        $this->actingAs($this->user)
            ->deleteJson(route('accounting-rules.destroy', $rule), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('accounting_rules', ['id' => $rule->id]);
        $this->assertSame($activeHeadCount, TransactionHead::query()->where('is_active', true)->count());
        $this->assertSame(0, TransactionHead::query()->whereNotNull('accounting_rule_id')->count());

        $head = TransactionHead::query()->where('code', 'TH-LRV')->firstOrFail();
        $headTransactionIds = Transaction::query()->where('transaction_head_id', $head->id)->pluck('id');

        $this->actingAs($this->user)
            ->deleteJson(route('transaction-heads.destroy', $head), ['confirmed' => true])
            ->assertOk();

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
            ->where('category', TransactionTypes::SUPPLIER_PAYMENT)
            ->firstOrFail();

        $this->actingAs($this->user)
            ->deleteJson(route('master.voucher-sequences.destroy', $sequence), ['confirmed' => true])
            ->assertOk();

        $this->assertDatabaseMissing('document_sequences', ['id' => $sequence->id]);
    }
}
