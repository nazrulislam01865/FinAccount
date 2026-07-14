<?php

namespace Tests\Feature\Feed;

use App\Models\AccountingRule;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedWarehouse;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Support\TransactionTypes;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeedCoaTransactionHeadPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_setup_uses_purchase_and_sale_heads_with_head_specific_rules(): void
    {
        [$user] = $this->seedFeedCompany();

        $this->actingAs($user)
            ->get(route('feed.setup.index'))
            ->assertOk()
            ->assertSee('COA and Transaction Head posting is active.')
            ->assertDontSee('Accounting Connection')
            ->assertDontSee('feed.setup.settings.store');

        $settings = FeedSetting::query()
            ->with(['purchaseTransactionHead', 'saleTransactionHead'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $this->assertSame('SYS-FEED-PUR', $settings->purchaseTransactionHead->code);
        $this->assertSame('SYS-FEED-SAL', $settings->saleTransactionHead->code);
        $this->assertSame(TransactionTypes::PURCHASE, $settings->purchaseTransactionHead->category);
        $this->assertSame(TransactionTypes::SALE, $settings->saleTransactionHead->category);

        foreach ([
            $settings->purchaseTransactionHead->id => TransactionTypes::PURCHASE,
            $settings->saleTransactionHead->id => TransactionTypes::SALE,
        ] as $headId => $category) {
            $rules = AccountingRule::query()
                ->where('company_id', $user->company_id)
                ->where('transaction_head_id', $headId)
                ->where('category', $category)
                ->where('is_active', true)
                ->pluck('settlement_type')
                ->sort()
                ->values()
                ->all();

            $this->assertSame(
                collect(TransactionTypes::ALL_SETTLEMENTS)->sort()->values()->all(),
                $rules,
            );
        }

        $purchaseHeads = app(TransactionEntryOptionService::class)
            ->transactionHeads((int) $user->company_id, TransactionTypes::PURCHASE);
        $saleHeads = app(TransactionEntryOptionService::class)
            ->transactionHeads((int) $user->company_id, TransactionTypes::SALE);

        $this->assertFalse($purchaseHeads->contains('id', $settings->purchase_transaction_head_id));
        $this->assertFalse($saleHeads->contains('id', $settings->sale_transaction_head_id));
    }

    public function test_feed_purchase_posts_through_purchase_head_and_head_specific_rule(): void
    {
        [$user, $warehouse, $item] = $this->seedFeedCompany();
        $supplier = Party::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'S-001')
            ->firstOrFail();
        $cash = MoneyAccount::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Main Cash Box')
            ->firstOrFail();

        $this->actingAs($user)->post(route('feed.purchases.store'), [
            'transaction_date' => now()->toDateString(),
            'party_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'money_account_id' => $cash->id,
            'external_invoice_no' => 'FEED-BUY-001',
            'transport_cost' => '100.00',
            'other_cost' => '0.00',
            'cost_allocation' => 'quantity',
            'paid_amount' => '600.00',
            'request_token' => (string) Str::uuid(),
            'lines' => [[
                'item_id' => $item->id,
                'unit' => 'BAG',
                'quantity' => '2.0000',
                'rate' => '1000.00',
                'discount' => '0.00',
            ]],
        ])->assertRedirect(route('feed.inventory.index'));

        $transaction = Transaction::query()
            ->where('company_id', $user->company_id)
            ->where('reference', 'FEED-BUY-001')
            ->firstOrFail();
        $settings = FeedSetting::query()
            ->with('purchaseTransactionHead.postingAccount')
            ->where('company_id', $user->company_id)
            ->firstOrFail();
        $lines = $transaction->journalEntry->lines;

        $this->assertSame(TransactionTypes::PARTIAL, $transaction->settlement_type);
        $this->assertSame('2100.00', number_format((float) $transaction->amount, 2, '.', ''));
        $this->assertSame('600.00', number_format((float) $transaction->paid_amount, 2, '.', ''));
        $this->assertSame('1500.00', number_format((float) $transaction->due_amount, 2, '.', ''));
        $this->assertSame('2100.00', number_format((float) $lines->sum('debit'), 2, '.', ''));
        $this->assertSame('2100.00', number_format((float) $lines->sum('credit'), 2, '.', ''));

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'chart_of_account_id' => $settings->purchaseTransactionHead->posting_account_id,
            'debit' => '2100.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'chart_of_account_id' => $cash->chart_of_account_id,
            'debit' => '0.00',
            'credit' => '600.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'chart_of_account_id' => $supplier->payable_account_id,
            'party_id' => $supplier->id,
            'debit' => '0.00',
            'credit' => '1500.00',
        ]);

        $balance = FeedStockBalance::query()
            ->where('feed_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $this->assertSame('100.0000', number_format((float) $balance->quantity, 4, '.', ''));
        $this->assertSame('21.000000', number_format((float) $balance->average_cost, 6, '.', ''));
    }

    public function test_feed_sale_posts_through_sale_head_and_head_specific_rule_with_cogs(): void
    {
        [$user, $warehouse, $item] = $this->seedFeedCompany();
        $supplier = Party::query()->where('company_id', $user->company_id)->where('code', 'S-001')->firstOrFail();
        $customer = Party::query()->where('company_id', $user->company_id)->where('code', 'C-001')->firstOrFail();
        $cash = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'Main Cash Box')->firstOrFail();

        $this->actingAs($user)->post(route('feed.purchases.store'), [
            'transaction_date' => now()->toDateString(),
            'party_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'money_account_id' => $cash->id,
            'transport_cost' => '100.00',
            'other_cost' => '0.00',
            'cost_allocation' => 'quantity',
            'paid_amount' => '2100.00',
            'request_token' => (string) Str::uuid(),
            'lines' => [[
                'item_id' => $item->id,
                'unit' => 'BAG',
                'quantity' => '2.0000',
                'rate' => '1000.00',
                'discount' => '0.00',
            ]],
        ])->assertRedirect(route('feed.inventory.index'));

        $this->actingAs($user)->post(route('feed.sales.store'), [
            'transaction_date' => now()->toDateString(),
            'party_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'money_account_id' => $cash->id,
            'reference' => 'FEED-SELL-001',
            'delivery_charge' => '100.00',
            'overall_discount' => '100.00',
            'paid_amount' => '500.00',
            'request_token' => (string) Str::uuid(),
            'lines' => [[
                'item_id' => $item->id,
                'unit' => 'BAG',
                'quantity' => '1.0000',
                'rate' => '1300.00',
                'discount' => '0.00',
            ]],
        ])->assertRedirect(route('feed.inventory.index'));

        $transaction = Transaction::query()
            ->where('company_id', $user->company_id)
            ->where('reference', 'FEED-SELL-001')
            ->firstOrFail();
        $settings = FeedSetting::query()
            ->with(['purchaseTransactionHead.postingAccount', 'saleTransactionHead.postingAccount', 'cogsAccount'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();
        $journal = $transaction->journalEntry;
        $lines = $journal->lines;

        $this->assertSame(TransactionTypes::PARTIAL, $transaction->settlement_type);
        $this->assertSame('1300.00', number_format((float) $transaction->amount, 2, '.', ''));
        $this->assertSame('500.00', number_format((float) $transaction->paid_amount, 2, '.', ''));
        $this->assertSame('800.00', number_format((float) $transaction->due_amount, 2, '.', ''));
        $this->assertSame('2350.00', number_format((float) $lines->sum('debit'), 2, '.', ''));
        $this->assertSame('2350.00', number_format((float) $lines->sum('credit'), 2, '.', ''));
        $this->assertCount(5, $lines);

        $expectedLines = [
            [$cash->chart_of_account_id, '500.00', '0.00', null],
            [$customer->receivable_account_id, '800.00', '0.00', $customer->id],
            [$settings->saleTransactionHead->posting_account_id, '0.00', '1300.00', null],
            [$settings->cogs_account_id, '1050.00', '0.00', null],
            [$settings->purchaseTransactionHead->posting_account_id, '0.00', '1050.00', null],
        ];

        foreach ($expectedLines as [$accountId, $debit, $credit, $partyId]) {
            $query = JournalLine::query()
                ->where('journal_entry_id', $journal->id)
                ->where('chart_of_account_id', $accountId)
                ->where('debit', $debit)
                ->where('credit', $credit);

            $partyId === null ? $query->whereNull('party_id') : $query->where('party_id', $partyId);
            $this->assertTrue($query->exists());
        }

        $balance = FeedStockBalance::query()
            ->where('feed_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $this->assertSame('50.0000', number_format((float) $balance->quantity, 4, '.', ''));
        $this->assertSame('21.000000', number_format((float) $balance->average_cost, 6, '.', ''));
        $this->assertDatabaseHas('sales_invoices', [
            'transaction_id' => $transaction->id,
            'status' => SalesInvoice::STATUS_PARTIAL,
            'paid_amount' => '500.00',
            'due_amount' => '800.00',
        ]);
    }

    /** @return array{0:User,1:FeedWarehouse,2:FeedItem} */
    private function seedFeedCompany(): array
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $warehouse = FeedWarehouse::query()->create([
            'company_id' => $user->company_id,
            'code' => 'TEST-WH',
            'name' => 'Test Feed Warehouse',
            'location' => 'Test location',
            'is_active' => true,
        ]);
        $item = FeedItem::query()->create([
            'company_id' => $user->company_id,
            'code' => 'TEST-FEED',
            'name' => 'Test Feed Item',
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

        return [$user, $warehouse, $item];
    }
}
