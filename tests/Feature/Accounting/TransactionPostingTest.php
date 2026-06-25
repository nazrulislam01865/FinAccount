<?php

namespace Tests\Feature\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Support\TransactionTypes;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_sale_uses_the_automatic_sale_cash_template(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $head = TransactionHead::query()->where('company_id', $user->company_id)->where('code', 'TH-SALE')->firstOrFail();
        $money = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'Main Cash Box')->firstOrFail();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'category' => TransactionTypes::SALE,
            // The visible payment selector no longer controls posting. Equal amounts mean CASH.
            'settlement_type' => TransactionTypes::CREDIT,
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $head->id,
            'money_account_id' => $money->id,
            'amount' => '1250.00',
            'paid_amount' => '1250.00',
            'reference' => 'TEST-CASH-SALE',
            'request_token' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('transactions.index'));
        $transaction = Transaction::query()->where('reference', 'TEST-CASH-SALE')->firstOrFail();
        $journal = JournalEntry::query()->where('transaction_id', $transaction->id)->firstOrFail();
        $lines = JournalLine::query()->where('journal_entry_id', $journal->id)->get();

        $this->assertCount(2, $lines);
        $this->assertSame('1250.00', number_format((float) $lines->sum('debit'), 2, '.', ''));
        $this->assertSame('1250.00', number_format((float) $lines->sum('credit'), 2, '.', ''));
        $this->assertDatabaseHas('sales_invoices', ['transaction_id' => $transaction->id, 'status' => SalesInvoice::STATUS_PAID]);
    }

    public function test_partial_sale_creates_money_receivable_and_income_lines(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $head = TransactionHead::query()->where('company_id', $user->company_id)->where('code', 'TH-SALE')->firstOrFail();
        $money = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'Main Cash Box')->firstOrFail();
        $customer = Party::query()->where('company_id', $user->company_id)->where('code', 'C-001')->firstOrFail();

        $this->actingAs($user)->post(route('transactions.store'), [
            'category' => TransactionTypes::SALE,
            // Less than the total means PARTIAL even when the hidden value says CASH.
            'settlement_type' => TransactionTypes::CASH,
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $head->id,
            'money_account_id' => $money->id,
            'party_id' => $customer->id,
            'amount' => '10000.00',
            'paid_amount' => '4000.00',
            'reference' => 'TEST-PARTIAL-SALE',
            'request_token' => (string) Str::uuid(),
        ])->assertRedirect(route('transactions.index'));

        $transaction = Transaction::query()->where('reference', 'TEST-PARTIAL-SALE')->firstOrFail();
        $lines = $transaction->journalEntry->lines;

        $this->assertCount(3, $lines);
        $this->assertSame('4000.00', number_format((float) $transaction->paid_amount, 2, '.', ''));
        $this->assertSame('6000.00', number_format((float) $transaction->due_amount, 2, '.', ''));
        $this->assertSame('10000.00', number_format((float) $lines->sum('debit'), 2, '.', ''));
        $this->assertSame('10000.00', number_format((float) $lines->sum('credit'), 2, '.', ''));
        $this->assertDatabaseHas('sales_invoices', ['transaction_id' => $transaction->id, 'status' => SalesInvoice::STATUS_PARTIAL]);
    }


    public function test_zero_received_now_creates_a_credit_sale_automatically(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $head = TransactionHead::query()->where('company_id', $user->company_id)->where('code', 'TH-SALE')->firstOrFail();
        $customer = Party::query()->where('company_id', $user->company_id)->where('code', 'C-001')->firstOrFail();

        $this->actingAs($user)->post(route('transactions.store'), [
            'category' => TransactionTypes::SALE,
            'settlement_type' => TransactionTypes::CASH,
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $head->id,
            'party_id' => $customer->id,
            'amount' => '8000.00',
            'paid_amount' => '0.00',
            'reference' => 'TEST-CREDIT-SALE',
            'request_token' => (string) Str::uuid(),
        ])->assertRedirect(route('transactions.index'));

        $transaction = Transaction::query()->where('reference', 'TEST-CREDIT-SALE')->firstOrFail();

        $this->assertSame(TransactionTypes::CREDIT, $transaction->settlement_type);
        $this->assertSame('0.00', number_format((float) $transaction->paid_amount, 2, '.', ''));
        $this->assertSame('8000.00', number_format((float) $transaction->due_amount, 2, '.', ''));
        $this->assertCount(2, $transaction->journalEntry->lines);
        $this->assertDatabaseHas('sales_invoices', [
            'transaction_id' => $transaction->id,
            'status' => SalesInvoice::STATUS_UNPAID,
        ]);
    }


    public function test_unique_matching_worker_is_selected_automatically_for_partial_salary_expense(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $head = TransactionHead::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'TH-EXP-SAL')
            ->firstOrFail();
        $money = MoneyAccount::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'BRAC Bank - Farm Account')
            ->firstOrFail();
        $worker = Party::query()
            ->where('company_id', $user->company_id)
            ->where('type', 'Worker')
            ->sole();

        $this->actingAs($user)->post(route('transactions.store'), [
            'category' => TransactionTypes::EXPENSE,
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $head->id,
            'money_account_id' => $money->id,
            'amount' => '1000.00',
            'paid_amount' => '500.00',
            'reference' => 'TEST-AUTO-WORKER',
            'request_token' => (string) Str::uuid(),
        ])->assertRedirect(route('transactions.index'));

        $transaction = Transaction::query()
            ->where('reference', 'TEST-AUTO-WORKER')
            ->firstOrFail();

        $this->assertSame($worker->id, $transaction->party_id);
        $this->assertSame(TransactionTypes::PARTIAL, $transaction->settlement_type);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transaction->journalEntry->id,
            'party_id' => $worker->id,
            'credit' => '500.00',
        ]);
    }

    public function test_the_same_request_token_is_not_posted_twice(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $head = TransactionHead::query()->where('company_id', $user->company_id)->where('code', 'TH-SALE')->firstOrFail();
        $money = MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'Main Cash Box')->firstOrFail();
        $token = (string) Str::uuid();
        $payload = [
            'category' => TransactionTypes::SALE,
            'settlement_type' => TransactionTypes::CASH,
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $head->id,
            'money_account_id' => $money->id,
            'amount' => '500.00',
            'request_token' => $token,
        ];

        $this->actingAs($user)->post(route('transactions.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('transactions.store'), $payload)->assertRedirect();

        $this->assertSame(1, Transaction::query()->where('request_token', $token)->count());
    }
}
