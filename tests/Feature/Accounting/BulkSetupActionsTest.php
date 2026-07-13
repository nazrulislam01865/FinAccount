<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingRule;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Support\TransactionTypes;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkSetupActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HisebGhorDemoSeeder::class);
        $this->user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
    }

    public function test_transaction_head_and_accounting_rule_pages_show_bulk_controls(): void
    {
        $this->actingAs($this->user)
            ->get(route('transaction-heads.index'))
            ->assertOk()
            ->assertSee('Choose bulk action')
            ->assertSee('Set Active')
            ->assertSee('Set Inactive')
            ->assertSee('Delete Permanently');

        $this->actingAs($this->user)
            ->get(route('accounting-rules.index'))
            ->assertOk()
            ->assertSee('Choose bulk action')
            ->assertSee('Set Active')
            ->assertSee('Set Inactive')
            ->assertSee('Delete Permanently');
    }

    public function test_transaction_heads_can_be_bulk_deactivated_and_activated(): void
    {
        $heads = TransactionHead::query()
            ->whereIn('code', ['TH-EXP-SAL', 'TH-EXP-NET'])
            ->get();

        $this->actingAs($this->user)
            ->post(route('transaction-heads.bulk-action'), [
                'bulk_action' => 'deactivate',
                'record_ids' => $heads->pluck('id')->all(),
            ])
            ->assertRedirect(route('transaction-heads.index'));

        $this->assertSame(0, TransactionHead::query()->whereIn('id', $heads->pluck('id'))->where('is_active', true)->count());

        $this->actingAs($this->user)
            ->post(route('transaction-heads.bulk-action'), [
                'bulk_action' => 'activate',
                'record_ids' => $heads->pluck('id')->all(),
            ])
            ->assertRedirect(route('transaction-heads.index'));

        $this->assertSame(2, TransactionHead::query()->whereIn('id', $heads->pluck('id'))->where('is_active', true)->count());
    }

    public function test_incomplete_transaction_head_cannot_be_bulk_activated(): void
    {
        $head = TransactionHead::query()->where('code', 'TH-EXP-NET')->firstOrFail();
        $head->postingAccount()->update(['is_active' => false]);
        $head->update(['is_active' => false]);

        $this->actingAs($this->user)
            ->postJson(route('transaction-heads.bulk-action'), [
                'bulk_action' => 'activate',
                'record_ids' => [$head->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('record_ids');

        $this->assertDatabaseHas('transaction_heads', [
            'id' => $head->id,
            'is_active' => false,
        ]);
    }

    public function test_transaction_heads_can_be_bulk_previewed_and_safely_deleted(): void
    {
        $heads = TransactionHead::query()
            ->whereIn('code', ['TH-LRV', 'TH-LRP'])
            ->get();
        $transactionIds = Transaction::query()
            ->whereIn('transaction_head_id', $heads->pluck('id'))
            ->pluck('id');

        $this->actingAs($this->user)
            ->postJson(route('transaction-heads.bulk-action'), [
                'bulk_action' => 'delete',
                'record_ids' => $heads->pluck('id')->all(),
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('plan.entity_type', 'Transaction Head Bulk Delete');

        $this->assertSame(2, TransactionHead::query()->whereIn('id', $heads->pluck('id'))->count());

        $this->actingAs($this->user)
            ->postJson(route('transaction-heads.bulk-action'), [
                'bulk_action' => 'delete',
                'record_ids' => $heads->pluck('id')->all(),
                'confirmed' => true,
            ])
            ->assertOk();

        $this->assertSame(0, TransactionHead::query()->whereIn('id', $heads->pluck('id'))->count());

        foreach ($transactionIds as $transactionId) {
            $this->assertDatabaseHas('transactions', [
                'id' => $transactionId,
                'transaction_head_id' => null,
                'status' => 'incomplete',
            ]);
        }
    }

    public function test_accounting_rules_can_be_bulk_deactivated_and_activated(): void
    {
        $rules = AccountingRule::query()
            ->whereIn('category', [TransactionTypes::SALE, TransactionTypes::EXPENSE])
            ->where('settlement_type', TransactionTypes::CASH)
            ->get();

        $this->actingAs($this->user)
            ->post(route('accounting-rules.bulk-action'), [
                'bulk_action' => 'deactivate',
                'record_ids' => $rules->pluck('id')->all(),
            ])
            ->assertRedirect(route('accounting-rules.index'));

        $this->assertSame(0, AccountingRule::query()->whereIn('id', $rules->pluck('id'))->where('is_active', true)->count());

        $this->actingAs($this->user)
            ->post(route('accounting-rules.bulk-action'), [
                'bulk_action' => 'activate',
                'record_ids' => $rules->pluck('id')->all(),
            ])
            ->assertRedirect(route('accounting-rules.index'));

        $this->assertSame($rules->count(), AccountingRule::query()->whereIn('id', $rules->pluck('id'))->where('is_active', true)->count());
    }

    public function test_incomplete_accounting_rule_cannot_be_bulk_activated(): void
    {
        $rule = AccountingRule::query()
            ->where('category', TransactionTypes::SALE)
            ->where('settlement_type', TransactionTypes::CASH)
            ->firstOrFail();
        $rule->lines()->delete();
        $rule->update(['is_active' => false]);

        $this->actingAs($this->user)
            ->postJson(route('accounting-rules.bulk-action'), [
                'bulk_action' => 'activate',
                'record_ids' => [$rule->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('record_ids');

        $this->assertDatabaseHas('accounting_rules', [
            'id' => $rule->id,
            'is_active' => false,
        ]);
    }

    public function test_accounting_rules_can_be_bulk_previewed_and_deleted(): void
    {
        $rules = AccountingRule::query()
            ->where('category', TransactionTypes::ASSET_PURCHASE)
            ->whereIn('settlement_type', [TransactionTypes::CASH, TransactionTypes::CREDIT])
            ->get();
        $lineIds = $rules->flatMap(fn (AccountingRule $rule) => $rule->lines()->pluck('id'));

        $this->actingAs($this->user)
            ->postJson(route('accounting-rules.bulk-action'), [
                'bulk_action' => 'delete',
                'record_ids' => $rules->pluck('id')->all(),
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('plan.entity_type', 'Accounting Rule Bulk Delete');

        $this->actingAs($this->user)
            ->postJson(route('accounting-rules.bulk-action'), [
                'bulk_action' => 'delete',
                'record_ids' => $rules->pluck('id')->all(),
                'confirmed' => true,
            ])
            ->assertOk();

        $this->assertSame(0, AccountingRule::query()->whereIn('id', $rules->pluck('id'))->count());
        $this->assertDatabaseMissing('accounting_rule_lines', ['id' => $lineIds->first()]);
    }
}
