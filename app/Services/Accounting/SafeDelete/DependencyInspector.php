<?php

namespace App\Services\Accounting\SafeDelete;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;

class DependencyInspector
{
    public function chartOfAccount(ChartOfAccount $account): DeletionPlan
    {
        return new DeletionPlan('Chart of Account', $account->code.' — '.$account->name, $this->nonZero([
            ['Money Accounts', $account->moneyAccounts()->count(), 'Mapped COA will be cleared and those money accounts will become inactive.'],
            ['Parties (receivable)', $account->receivableParties()->count(), 'Receivable mapping will be cleared and those parties will become inactive.'],
            ['Parties (payable/capital)', $account->payableParties()->count(), 'Payable/capital mapping will be cleared and those parties will become inactive.'],
            ['Transaction Heads', $account->transactionHeads()->count(), 'Posting COA will be cleared and those transaction heads will become inactive.'],
            ['Journal Lines', $account->journalLines()->count(), 'The COA link will be cleared and affected transactions/journals will become incomplete.'],
        ]));
    }

    public function moneyAccount(MoneyAccount $account): DeletionPlan
    {
        return new DeletionPlan('Money Account', $account->name, $this->nonZero([
            ['Transactions', $account->transactions()->count(), 'Money-account links will be cleared and transactions will become incomplete.'],
            ['Journal Lines', JournalLine::query()->where('money_account_id', $account->id)->count(), 'Money-account links will be cleared.'],
        ]));
    }

    public function party(Party $party): DeletionPlan
    {
        return new DeletionPlan('Party', $party->code.' — '.$party->name, $this->nonZero([
            ['Transactions', $party->transactions()->count(), 'Party links will be cleared and transactions will become incomplete.'],
            ['Journal Lines', JournalLine::query()->where('party_id', $party->id)->count(), 'Party links will be cleared.'],
        ]));
    }

    public function accountingRule(AccountingRule $rule): DeletionPlan
    {
        $headIds = $rule->transactionHeads()->pluck('id');

        return new DeletionPlan('Accounting Rule', $rule->code.' — '.$rule->name, $this->nonZero([
            ['Transaction Heads', $headIds->count(), 'Rule links will be cleared and those transaction heads will become inactive.'],
            ['Transactions using those heads', Transaction::query()->whereIn('transaction_head_id', $headIds)->count(), 'Transactions and journals will become incomplete until repaired.'],
        ]));
    }

    public function transactionHead(TransactionHead $head): DeletionPlan
    {
        return new DeletionPlan('Transaction Head', $head->code.' — '.$head->name, $this->nonZero([
            ['Transactions', $head->transactions()->count(), 'Transaction-head links will be cleared and transactions/journals will become incomplete.'],
        ]));
    }

    public function accountingOption(AccountingOption $option): DeletionPlan
    {
        $dependencies = match ($option->option_group) {
            AccountingOption::GROUP_PARTY_TYPE => [
                ['Parties', Party::query()->where('type', $option->value)->count(), 'Party type will be cleared and parties will become inactive.'],
                ['Accounting Rules', AccountingRule::query()->where('party_type', $option->value)->count(), 'Required party type will be cleared and rules will become inactive.'],
            ],
            AccountingOption::GROUP_MONEY_ACCOUNT_KIND => [
                ['Money Accounts', MoneyAccount::query()->where('kind', $option->value)->count(), 'Money-account type will be cleared and accounts will become inactive.'],
            ],
            AccountingOption::GROUP_TRANSACTION_CATEGORY => [
                ['Accounting Rules', AccountingRule::query()->where('category', $option->value)->count(), 'Category will be cleared and rules will become inactive.'],
                ['Transaction Heads', TransactionHead::query()->where('category', $option->value)->count(), 'Category will be cleared and heads will become inactive.'],
                ['Voucher Numbering', DocumentSequence::query()->where('category', $option->value)->count(), 'Category will be cleared and numbering will become inactive.'],
                ['Transactions', Transaction::query()->where('category', $option->value)->count(), 'Category/head links will be cleared and transactions/journals will become incomplete.'],
            ],
            default => [],
        };

        return new DeletionPlan('Master Value', $option->label.' ('.$option->value.')', $this->nonZero($dependencies));
    }


    public function transaction(Transaction $transaction): DeletionPlan
    {
        $journalEntry = $transaction->journalEntry()->withCount('lines')->first();

        return new DeletionPlan(
            'Transaction',
            $transaction->voucher_no,
            $this->nonZero([
                ['Generated Journal Entry', $journalEntry ? 1 : 0, 'The generated journal entry will be permanently deleted with the transaction.'],
                ['Generated Journal Lines', (int) ($journalEntry?->lines_count ?? 0), 'The generated debit/credit lines will be permanently deleted with the transaction.'],
                ['Generated Sales Invoice', $transaction->salesInvoice()->exists() ? 1 : 0, 'The customer-facing invoice generated from this sales transaction will also be deleted.'],
            ]),
            'The transaction and its generated journal records will be permanently deleted from the database.',
            'Journal entries, journal lines, attachments, and generated sales invoices are children of the transaction, so they will be deleted together rather than left unlinked.',
        );
    }

    public function voucherSequence(DocumentSequence $sequence): DeletionPlan
    {
        $label = ($sequence->category ?: 'Unlinked').' / '.$sequence->prefix;

        return new DeletionPlan('Voucher Numbering', $label, []);
    }

    /**
     * @param array<int, array{0:string,1:int,2:string}> $items
     * @return array<int, array{label:string,count:int,effect:string}>
     */
    private function nonZero(array $items): array
    {
        $result = [];

        foreach ($items as [$label, $count, $effect]) {
            if ($count > 0) {
                $result[] = compact('label', 'count', 'effect');
            }
        }

        return $result;
    }
}
