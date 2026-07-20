<?php

namespace App\Services\Accounting\SafeDelete;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\TransactionHead;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedBusinessTrackingUnit;
use App\Models\Feed\FeedWarehouse;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedStockMovement;
use App\Models\Feed\FeedBusinessTrackingDefaultAssignment;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedDocument;
use App\Models\Feed\FeedDocumentLine;
use Illuminate\Support\Collection;

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
            ['Feed Settings', FeedSetting::query()->where('cogs_account_id', $account->id)->count(), 'The Feed COGS account configuration will be cleared.'],
            ['Opening Balances', OpeningBalance::query()->where('chart_of_account_id', $account->id)->count(), 'Opening balance rows mapped to this COA will be deleted.'],
        ]));
    }

    public function moneyAccount(MoneyAccount $account): DeletionPlan
    {
        return new DeletionPlan('Money Account', $account->name, $this->nonZero([
            ['Transactions', $account->transactions()->count(), 'Money-account links will be cleared and transactions will become incomplete.'],
            ['Payment Splits', TransactionPayment::query()->where('money_account_id', $account->id)->count(), 'Payment-breakdown rows will be removed and affected transactions will become incomplete.'],
            ['Journal Lines', JournalLine::query()->where('money_account_id', $account->id)->count(), 'Money-account links will be cleared.'],
            ['Opening Balances', OpeningBalance::query()->where('money_account_id', $account->id)->count(), 'Money-account link will be cleared from opening rows.'],
        ]));
    }

    public function party(Party $party): DeletionPlan
    {
        return new DeletionPlan('Party', $party->code.' — '.$party->name, $this->nonZero([
            ['Transactions', $party->transactions()->count(), 'Party links will be cleared and transactions will become incomplete.'],
            ['Journal Lines', JournalLine::query()->where('party_id', $party->id)->count(), 'Party links will be cleared.'],
            ['Opening Balances', OpeningBalance::query()->where('party_id', $party->id)->count(), 'Party link will be cleared from opening rows.'],
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
        $legacyRuleCount = $head->accounting_rule_id ? 1 : 0;

        return new DeletionPlan('Transaction Head', $head->code.' — '.$head->name, $this->nonZero([
            ['Accounting Rules', $head->accountingRules()->count() + $legacyRuleCount, 'Head-specific accounting rules will be deleted and legacy rule links will be cleared.'],
            ['Transactions', $head->transactions()->count(), 'Transaction-head links will be cleared and transactions/journals will become incomplete.'],
            ['Feed Settings', FeedSetting::query()->where('purchase_transaction_head_id', $head->id)->orWhere('sale_transaction_head_id', $head->id)->count(), 'The Feed purchase or sale transaction head configuration will be cleared.'],
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
                ['Payment Breakdown Rows', $transaction->payments()->count(), 'The stored payment-method breakdown will be permanently deleted with the transaction.'],
                ['Generated Sales Invoice', $transaction->salesInvoice()->exists() ? 1 : 0, 'The customer-facing invoice generated from this sales transaction will also be deleted.'],
                ['Feed Inventory Document', $transaction->feedDocument()->exists() ? 1 : 0, 'Feed item lines and stock movements will be reversed before deletion. A feed transaction can only be deleted when no later stock movement depends on it.'],
            ]),
            'The transaction and its generated journal records will be permanently deleted from the database.',
            'Journal entries, journal lines, attachments, generated sales invoices, and feed stock movements are deleted together. Feed stock is restored to its exact balance before the transaction.',
        );
    }

    public function voucherSequence(DocumentSequence $sequence): DeletionPlan
    {
        $label = ($sequence->category ?: 'Unlinked').' / '.$sequence->prefix;

        return new DeletionPlan('Voucher Numbering', $label, []);
    }

    public function feedItem(FeedItem $item): DeletionPlan
    {
        $documentIds = FeedDocumentLine::query()
            ->where('feed_item_id', $item->id)
            ->pluck('feed_document_id')
            ->unique()
            ->values();

        return new DeletionPlan('Feed Item', $item->code.' — '.$item->name, $this->nonZero([
            ['Feed Document Lines', FeedDocumentLine::query()->where('feed_item_id', $item->id)->count(), 'Purchase/sale lines that use this item will be permanently deleted.'],
            ['Feed Documents', $documentIds->count(), 'Feed purchase/sale documents using this item will be permanently deleted and their transactions will become incomplete.'],
            ['Stock Balances', FeedStockBalance::query()->where('feed_item_id', $item->id)->count(), 'Stock balances will be permanently deleted.'],
            ['Stock Movements', FeedStockMovement::query()->where('feed_item_id', $item->id)->count(), 'Stock movements will be permanently deleted and related transactions marked incomplete.'],
        ]));
    }

    public function feedBusinessTrackingUnit(FeedBusinessTrackingUnit $unit): DeletionPlan
    {
        $unitIds = $this->feedBusinessTrackingUnitTreeIds($unit);
        $childCount = max($unitIds->count() - 1, 0);

        return new DeletionPlan('Business Tracking Unit', $unit->code.' — '.$unit->name, $this->nonZero([
            ['Child Units', $childCount, 'Child tracking units under this record will also be permanently deleted.'],
            ['Transactions', Transaction::query()->whereIn('tracking_unit_id', $unitIds)->count(), 'Tracking unit links will be cleared and transactions will become incomplete.'],
            ['Feed Documents', FeedDocument::query()->whereIn('tracking_unit_id', $unitIds)->count(), 'Feed purchase/sale documents linked to this tracking unit will be permanently deleted.'],
            ['Stock Balances', FeedStockBalance::query()->whereIn('tracking_unit_id', $unitIds)->count(), 'Stock balances will be permanently deleted.'],
            ['Stock Movements', FeedStockMovement::query()->whereIn('tracking_unit_id', $unitIds)->count(), 'Stock movements will be permanently deleted and related transactions marked incomplete.'],
            ['Default Assignments', FeedBusinessTrackingDefaultAssignment::query()->whereIn('business_tracking_unit_id', $unitIds)->count(), 'Default assignments will be permanently deleted.'],
            ['Feed Settings', FeedSetting::query()->whereIn('default_tracking_unit_id', $unitIds)->count(), 'Default tracking unit setting will be cleared.'],
        ]));
    }

    public function feedWarehouse(FeedWarehouse $warehouse): DeletionPlan
    {
        return new DeletionPlan('Warehouse', $warehouse->code.' — '.$warehouse->name, $this->nonZero([
            ['Transactions', Transaction::query()->where('tracking_unit_id', $warehouse->id)->count(), 'Warehouse links will be cleared and transactions will become incomplete.'],
            ['Feed Documents', FeedDocument::query()->where('tracking_unit_id', $warehouse->id)->count(), 'Feed purchase/sale documents linked to this warehouse will be permanently deleted.'],
            ['Stock Balances', FeedStockBalance::query()->where('tracking_unit_id', $warehouse->id)->count(), 'Stock balances will be permanently deleted.'],
            ['Stock Movements', FeedStockMovement::query()->where('tracking_unit_id', $warehouse->id)->count(), 'Stock movements will be permanently deleted and related transactions marked incomplete.'],
            ['Feed Settings', FeedSetting::query()->where('default_tracking_unit_id', $warehouse->id)->count(), 'Default warehouse setting will be cleared.'],
        ]));
    }

    private function feedBusinessTrackingUnitTreeIds(FeedBusinessTrackingUnit $unit): Collection
    {
        $ids = collect([(int) $unit->id]);
        $frontier = collect([(int) $unit->id]);

        while ($frontier->isNotEmpty()) {
            $children = FeedBusinessTrackingUnit::query()
                ->where('company_id', $unit->company_id)
                ->whereIn('parent_id', $frontier->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => $ids->contains($id))
                ->values();

            if ($children->isEmpty()) {
                break;
            }

            $ids = $ids->merge($children)->unique()->values();
            $frontier = $children;
        }

        return $ids;
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
