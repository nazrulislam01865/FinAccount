<?php

namespace Tests\Feature\Feed;

use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedWarehouse;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Feed\FeedAccountingSetupService;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeedMultiplePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_purchase_splits_payment_storage_and_credit_journal_lines(): void
    {
        [$user, $warehouse, $item, $settings] = $this->feedContext();
        $supplier = Party::query()->where('company_id', $user->company_id)->where('code', 'S-001')->firstOrFail();
        $cash = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'Main Cash Box')->firstOrFail();
        $bank = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'BRAC Bank - Farm Account')->firstOrFail();
        $token = (string) Str::uuid();

        $this->actingAs($user)->post(route('feed.purchases.store'), [
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $settings->purchase_transaction_head_id,
            'party_id' => $supplier->id,
            'tracking_unit_id' => $warehouse->id,
            'overall_discount' => '0',
            'transport_cost' => '100.00',
            'other_cost' => '0.00',
            'request_token' => $token,
            'payments' => [
                ['money_account_id' => $cash->id, 'amount' => '600.00'],
                ['money_account_id' => $bank->id, 'amount' => '400.00'],
            ],
            'lines' => [[
                'item_id' => $item->id,
                'unit' => 'BAG',
                'quantity' => '2.0000',
                'rate' => '1000.00',
            ]],
        ])->assertRedirect(route('feed.inventory.index'));

        $transaction = Transaction::query()->where('request_token', $token)->firstOrFail();

        $this->assertSame('1900.00', number_format((float) $transaction->amount, 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $transaction->paid_amount, 2, '.', ''));
        $this->assertSame('900.00', number_format((float) $transaction->due_amount, 2, '.', ''));
        $this->assertSame($cash->id, $transaction->money_account_id);
        $this->assertDatabaseHas('transaction_payments', [
            'transaction_id' => $transaction->id,
            'money_account_id' => $cash->id,
            'sequence' => 1,
            'amount' => '600.00',
        ]);
        $this->assertDatabaseHas('transaction_payments', [
            'transaction_id' => $transaction->id,
            'money_account_id' => $bank->id,
            'sequence' => 2,
            'amount' => '400.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'money_account_id' => $cash->id,
            'credit' => '600.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'money_account_id' => $bank->id,
            'credit' => '400.00',
        ]);
        $this->assertSame('1900.00', number_format((float) $transaction->journalEntry->lines->sum('debit'), 2, '.', ''));
        $this->assertSame('1900.00', number_format((float) $transaction->journalEntry->lines->sum('credit'), 2, '.', ''));

        $document = $transaction->feedDocument;
        $this->actingAs($user)
            ->get(route('feed.purchases.receipt', $document))
            ->assertOk()
            ->assertSee('Paid via Main Cash Box')
            ->assertSee('Paid via BRAC Bank - Farm Account');
    }

    public function test_feed_sale_splits_received_amount_and_keeps_cogs_journal_balanced(): void
    {
        [$user, $warehouse, $item, $settings] = $this->feedContext();
        $supplier = Party::query()->where('company_id', $user->company_id)->where('code', 'S-001')->firstOrFail();
        $customer = Party::query()->where('company_id', $user->company_id)->where('code', 'C-001')->firstOrFail();
        $cash = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'Main Cash Box')->firstOrFail();
        $bank = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'BRAC Bank - Farm Account')->firstOrFail();

        $this->actingAs($user)->post(route('feed.purchases.store'), [
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $settings->purchase_transaction_head_id,
            'party_id' => $supplier->id,
            'tracking_unit_id' => $warehouse->id,
            'overall_discount' => '0',
            'transport_cost' => '0.00',
            'other_cost' => '0.00',
            'request_token' => (string) Str::uuid(),
            'payments' => [['money_account_id' => $cash->id, 'amount' => '2000.00']],
            'lines' => [[
                'item_id' => $item->id,
                'unit' => 'BAG',
                'quantity' => '2.0000',
                'rate' => '1000.00',
            ]],
        ])->assertRedirect(route('feed.inventory.index'));

        $token = (string) Str::uuid();
        $this->actingAs($user)->post(route('feed.sales.store'), [
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $settings->sale_transaction_head_id,
            'party_id' => $customer->id,
            'tracking_unit_id' => $warehouse->id,
            'reference' => 'MULTI-SALE-001',
            'overall_discount' => '0',
            'transport_cost' => '100.00',
            'other_cost' => '0.00',
            'request_token' => $token,
            'payments' => [
                ['money_account_id' => $cash->id, 'amount' => '400.00'],
                ['money_account_id' => $bank->id, 'amount' => '600.00'],
            ],
            'lines' => [[
                'item_id' => $item->id,
                'unit' => 'BAG',
                'quantity' => '1.0000',
                'rate' => '1300.00',
            ]],
        ])->assertRedirect(route('feed.inventory.index'));

        $transaction = Transaction::query()->where('request_token', $token)->firstOrFail();
        $lines = $transaction->journalEntry->lines;

        $this->assertSame('1400.00', number_format((float) $transaction->amount, 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $transaction->paid_amount, 2, '.', ''));
        $this->assertSame('400.00', number_format((float) $transaction->due_amount, 2, '.', ''));
        $this->assertCount(2, $transaction->payments);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'money_account_id' => $cash->id,
            'debit' => '400.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'money_account_id' => $bank->id,
            'debit' => '600.00',
        ]);
        $this->assertSame('2400.00', number_format((float) $lines->sum('debit'), 2, '.', ''));
        $this->assertSame('2400.00', number_format((float) $lines->sum('credit'), 2, '.', ''));
    }

    /** @return array{User, FeedWarehouse, FeedItem, FeedSetting} */
    private function feedContext(): array
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $warehouse = FeedWarehouse::query()->create([
            'company_id' => $user->company_id,
            'code' => 'MULTI-PAY-WH',
            'name' => 'Multiple Payment Warehouse',
            'location' => 'Test location',
            'is_active' => true,
        ]);
        $item = FeedItem::query()->create([
            'company_id' => $user->company_id,
            'code' => 'MULTI-PAY-FEED',
            'name' => 'Multiple Payment Feed',
            'category' => 'Poultry',
            'brand' => 'Test',
            'pack_size' => '50.0000',
            'base_unit' => 'KG',
            'default_purchase_price' => '1000.00',
            'default_sale_price' => '1300.00',
            'reorder_level' => '1.0000',
            'track_batch' => false,
            'track_expiry' => false,
            'is_active' => true,
        ]);
        $settings = app(FeedAccountingSetupService::class)->ensure((int) $user->company_id);

        return [$user, $warehouse, $item, $settings];
    }
}
