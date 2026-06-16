<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_post_a_balanced_cash_sale(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);

        $company = Company::query()->where('code', 'HG-DEMO')->firstOrFail();
        $user = User::query()->where('company_id', $company->id)->firstOrFail();
        $head = TransactionHead::query()
            ->where('company_id', $company->id)
            ->where('code', 'TH-S-001')
            ->firstOrFail();
        $moneyAccountId = $company->id
            ? \App\Models\MoneyAccount::query()->where('company_id', $company->id)->where('name', 'Main Cash Box')->value('id')
            : null;

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'category' => 'Sales',
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $head->id,
            'money_account_id' => $moneyAccountId,
            'party_id' => null,
            'amount' => '1250.00',
            'reference' => 'TEST-SALE',
            'description' => 'Automated cash sale test',
            'request_token' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('transactions.index'));

        $transaction = Transaction::query()->where('reference', 'TEST-SALE')->firstOrFail();
        $journal = JournalEntry::query()->where('transaction_id', $transaction->id)->firstOrFail();
        $lines = JournalLine::query()->where('journal_entry_id', $journal->id)->get();

        $this->assertCount(2, $lines);
        $this->assertSame('1250.00', number_format((float) $lines->sum('debit'), 2, '.', ''));
        $this->assertSame('1250.00', number_format((float) $lines->sum('credit'), 2, '.', ''));
        $this->assertSame(8, Transaction::query()->where('company_id', $company->id)->count());
        $this->assertSame(4, DocumentSequence::query()->where('company_id', $company->id)->where('category', 'Sales')->value('next_number'));
    }

    public function test_the_same_request_token_is_not_posted_twice(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);

        $user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $head = TransactionHead::query()->where('company_id', $user->company_id)->where('code', 'TH-S-001')->firstOrFail();
        $moneyAccountId = \App\Models\MoneyAccount::query()->where('company_id', $user->company_id)->where('name', 'Main Cash Box')->value('id');
        $token = (string) Str::uuid();
        $payload = [
            'category' => 'Sales',
            'transaction_date' => now()->toDateString(),
            'transaction_head_id' => $head->id,
            'money_account_id' => $moneyAccountId,
            'amount' => '500.00',
            'request_token' => $token,
        ];

        $this->actingAs($user)->post(route('transactions.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('transactions.store'), $payload)->assertRedirect();

        $this->assertSame(1, Transaction::query()->where('request_token', $token)->count());
    }
}
