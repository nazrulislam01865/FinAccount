<?php

namespace App\Services\Accounting\SafeDelete;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedBusinessTrackingUnit;
use App\Models\Feed\FeedWarehouse;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedStockMovement;
use App\Models\Feed\FeedBusinessTrackingDefaultAssignment;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedDocument;

class DependencyDetacher
{
    public function chartOfAccount(ChartOfAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $locked = ChartOfAccount::query()->lockForUpdate()->findOrFail($account->id);
            $moneyIds = MoneyAccount::query()->where('chart_of_account_id', $locked->id)->pluck('id');
            $partyIds = Party::query()
                ->where(fn (Builder $query) => $query
                    ->where('receivable_account_id', $locked->id)
                    ->orWhere('payable_account_id', $locked->id))
                ->pluck('id');
            $headIds = TransactionHead::query()->where('posting_account_id', $locked->id)->pluck('id');
            $journalTransactionIds = JournalLine::query()
                ->where('chart_of_account_id', $locked->id)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->pluck('journal_entries.transaction_id');

            $transactionIds = collect();

            if ($moneyIds->isNotEmpty()) {
                $transactionIds = $transactionIds->merge(
                    Transaction::query()
                        ->where('company_id', $locked->company_id)
                        ->whereIn('money_account_id', $moneyIds)
                        ->pluck('id')
                );
            }

            if ($partyIds->isNotEmpty()) {
                $transactionIds = $transactionIds->merge(
                    Transaction::query()
                        ->where('company_id', $locked->company_id)
                        ->whereIn('party_id', $partyIds)
                        ->pluck('id')
                );
            }

            if ($headIds->isNotEmpty()) {
                $transactionIds = $transactionIds->merge(
                    Transaction::query()
                        ->where('company_id', $locked->company_id)
                        ->whereIn('transaction_head_id', $headIds)
                        ->pluck('id')
                );
            }

            $transactionIds = $transactionIds
                ->merge($journalTransactionIds)
                ->unique()
                ->values();

            $this->markTransactionsIncomplete($transactionIds);

            JournalLine::query()->where('chart_of_account_id', $locked->id)->update(['chart_of_account_id' => null]);
            MoneyAccount::query()->where('chart_of_account_id', $locked->id)->update([
                'chart_of_account_id' => null,
                'is_active' => false,
            ]);
            Party::query()->where('receivable_account_id', $locked->id)->update([
                'receivable_account_id' => null,
                'is_active' => false,
            ]);
            Party::query()->where('payable_account_id', $locked->id)->update([
                'payable_account_id' => null,
                'is_active' => false,
            ]);
            TransactionHead::query()->where('posting_account_id', $locked->id)->update([
                'posting_account_id' => null,
                'is_active' => false,
            ]);
            FeedSetting::query()->where('cogs_account_id', $locked->id)->update([
                'cogs_account_id' => null,
            ]);
            OpeningBalance::query()->where('chart_of_account_id', $locked->id)->delete();

            $this->assertNoReference(MoneyAccount::query()->where('chart_of_account_id', $locked->id), 'money account COA');
            $this->assertNoReference(Party::query()->where('receivable_account_id', $locked->id), 'party receivable COA');
            $this->assertNoReference(Party::query()->where('payable_account_id', $locked->id), 'party payable COA');
            $this->assertNoReference(TransactionHead::query()->where('posting_account_id', $locked->id), 'transaction head posting COA');
            $this->assertNoReference(JournalLine::query()->where('chart_of_account_id', $locked->id), 'journal line COA');
            $this->assertNoReference(OpeningBalance::query()->where('chart_of_account_id', $locked->id), 'opening balance COA');
            $this->assertInactive(MoneyAccount::class, $moneyIds, 'money accounts');
            $this->assertInactive(Party::class, $partyIds, 'parties');
            $this->assertInactive(TransactionHead::class, $headIds, 'transaction heads');
            $this->assertTransactionsIncomplete($transactionIds);

            $locked->delete();
            $this->assertDeleted(ChartOfAccount::class, $locked->id);
        }, attempts: 5);
    }

    public function moneyAccount(MoneyAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $locked = MoneyAccount::query()->lockForUpdate()->findOrFail($account->id);
            $transactionIds = Transaction::query()->where('money_account_id', $locked->id)->pluck('id');
            $this->markTransactionsIncomplete($transactionIds);
            Transaction::query()->where('money_account_id', $locked->id)->update(['money_account_id' => null]);
            JournalLine::query()->where('money_account_id', $locked->id)->update(['money_account_id' => null]);
            OpeningBalance::query()->where('money_account_id', $locked->id)->update(['money_account_id' => null]);
            $this->assertNoReference(Transaction::query()->where('money_account_id', $locked->id), 'transaction money account');
            $this->assertNoReference(JournalLine::query()->where('money_account_id', $locked->id), 'journal line money account');
            $this->assertNoReference(OpeningBalance::query()->where('money_account_id', $locked->id), 'opening balance money account');
            $this->assertTransactionsIncomplete($transactionIds);
            $locked->delete();
            $this->assertDeleted(MoneyAccount::class, $locked->id);
        }, attempts: 5);
    }

    public function party(Party $party): void
    {
        DB::transaction(function () use ($party): void {
            $locked = Party::query()->lockForUpdate()->findOrFail($party->id);
            $transactionIds = Transaction::query()->where('party_id', $locked->id)->pluck('id');
            $this->markTransactionsIncomplete($transactionIds);
            Transaction::query()->where('party_id', $locked->id)->update(['party_id' => null]);
            JournalLine::query()->where('party_id', $locked->id)->update(['party_id' => null]);
            OpeningBalance::query()->where('party_id', $locked->id)->update(['party_id' => null]);
            $this->assertNoReference(Transaction::query()->where('party_id', $locked->id), 'transaction party');
            $this->assertNoReference(JournalLine::query()->where('party_id', $locked->id), 'journal line party');
            $this->assertNoReference(OpeningBalance::query()->where('party_id', $locked->id), 'opening balance party');
            $this->assertTransactionsIncomplete($transactionIds);
            $locked->delete();
            $this->assertDeleted(Party::class, $locked->id);
        }, attempts: 5);
    }

    public function feedItem(FeedItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $locked = FeedItem::query()->lockForUpdate()->findOrFail($item->id);
            $transactionIds = FeedStockMovement::query()
                ->where('feed_item_id', $locked->id)
                ->pluck('transaction_id')
                ->unique()
                ->values();
            
            $this->markTransactionsIncomplete($transactionIds);
            
            FeedStockMovement::query()->where('feed_item_id', $locked->id)->delete();
            FeedStockBalance::query()->where('feed_item_id', $locked->id)->delete();
            
            $this->assertTransactionsIncomplete($transactionIds);
            
            $locked->delete();
            $this->assertDeleted(FeedItem::class, $locked->id);
        }, attempts: 5);
    }

    public function feedBusinessTrackingUnit(FeedBusinessTrackingUnit $unit): void
    {
        DB::transaction(function () use ($unit): void {
            $locked = FeedBusinessTrackingUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $unitIds = $this->feedBusinessTrackingUnitTreeIds($locked);

            FeedBusinessTrackingUnit::query()
                ->whereIn('id', $unitIds->all())
                ->lockForUpdate()
                ->get();

            $transactionIds1 = Transaction::query()->whereIn('tracking_unit_id', $unitIds)->pluck('id');
            $transactionIds2 = FeedStockMovement::query()->whereIn('tracking_unit_id', $unitIds)->pluck('transaction_id');
            $transactionIds3 = FeedDocument::query()->whereIn('tracking_unit_id', $unitIds)->pluck('transaction_id');
            $transactionIds = $transactionIds1
                ->merge($transactionIds2)
                ->merge($transactionIds3)
                ->unique()
                ->values();
            
            $this->markTransactionsIncomplete($transactionIds);
            
            Transaction::query()->whereIn('tracking_unit_id', $unitIds)->update(['tracking_unit_id' => null]);
            FeedStockMovement::query()->whereIn('tracking_unit_id', $unitIds)->delete();
            FeedStockBalance::query()->whereIn('tracking_unit_id', $unitIds)->delete();
            FeedDocument::query()->whereIn('tracking_unit_id', $unitIds)->delete();
            FeedBusinessTrackingDefaultAssignment::query()->whereIn('business_tracking_unit_id', $unitIds)->delete();
            FeedSetting::query()->whereIn('default_tracking_unit_id', $unitIds)->update([
                'default_tracking_unit_id' => null,
            ]);
            
            $this->assertNoReference(Transaction::query()->whereIn('tracking_unit_id', $unitIds), 'business tracking unit transactions');
            $this->assertNoReference(FeedStockMovement::query()->whereIn('tracking_unit_id', $unitIds), 'business tracking unit stock movements');
            $this->assertNoReference(FeedStockBalance::query()->whereIn('tracking_unit_id', $unitIds), 'business tracking unit stock balances');
            $this->assertNoReference(FeedDocument::query()->whereIn('tracking_unit_id', $unitIds), 'business tracking unit feed documents');
            $this->assertNoReference(FeedBusinessTrackingDefaultAssignment::query()->whereIn('business_tracking_unit_id', $unitIds), 'business tracking unit default assignments');
            $this->assertNoReference(FeedSetting::query()->whereIn('default_tracking_unit_id', $unitIds), 'business tracking unit feed settings');
            $this->assertTransactionsIncomplete($transactionIds);
            
            FeedBusinessTrackingUnit::query()->whereIn('id', $unitIds->all())->delete();

            foreach ($unitIds as $unitId) {
                $this->assertDeleted(FeedBusinessTrackingUnit::class, (int) $unitId);
            }
        }, attempts: 5);
    }

    public function feedWarehouse(FeedWarehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse): void {
            $locked = FeedWarehouse::query()->lockForUpdate()->findOrFail($warehouse->id);
            
            FeedSetting::query()->where('default_tracking_unit_id', $locked->id)->update([
                'default_tracking_unit_id' => null
            ]);
            
            $locked->delete();
            $this->assertDeleted(FeedWarehouse::class, $locked->id);
        }, attempts: 5);
    }

    public function accountingRule(AccountingRule $rule): void
    {
        DB::transaction(function () use ($rule): void {
            $locked = AccountingRule::query()->lockForUpdate()->findOrFail($rule->id);
            $headIds = TransactionHead::query()->where('accounting_rule_id', $locked->id)->pluck('id');
            $transactionIds = Transaction::query()->whereIn('transaction_head_id', $headIds)->pluck('id');
            $this->markTransactionsIncomplete($transactionIds);
            TransactionHead::query()->where('accounting_rule_id', $locked->id)->update([
                'accounting_rule_id' => null,
                'is_active' => false,
            ]);
            $this->assertNoReference(TransactionHead::query()->where('accounting_rule_id', $locked->id), 'transaction head accounting rule');
            $this->assertInactive(TransactionHead::class, $headIds, 'transaction heads');
            $this->assertTransactionsIncomplete($transactionIds);
            $locked->delete();
            $this->assertDeleted(AccountingRule::class, $locked->id);
        }, attempts: 5);
    }

    public function transactionHead(TransactionHead $head): void
    {
        DB::transaction(function () use ($head): void {
            $locked = TransactionHead::query()->lockForUpdate()->findOrFail($head->id);
            $transactionIds = Transaction::query()->where('transaction_head_id', $locked->id)->pluck('id');
            $this->markTransactionsIncomplete($transactionIds);
            Transaction::query()->where('transaction_head_id', $locked->id)->update(['transaction_head_id' => null]);
            
            FeedSetting::query()->where('purchase_transaction_head_id', $locked->id)->update([
                'purchase_transaction_head_id' => null,
            ]);
            FeedSetting::query()->where('sale_transaction_head_id', $locked->id)->update([
                'sale_transaction_head_id' => null,
            ]);

            $this->assertNoReference(Transaction::query()->where('transaction_head_id', $locked->id), 'transaction head');
            $this->assertTransactionsIncomplete($transactionIds);
            $locked->delete();
            $this->assertDeleted(TransactionHead::class, $locked->id);
        }, attempts: 5);
    }

    public function accountingOption(AccountingOption $option): void
    {
        DB::transaction(function () use ($option): void {
            $locked = AccountingOption::query()->lockForUpdate()->findOrFail($option->id);

            if ($locked->option_group === AccountingOption::GROUP_PARTY_TYPE) {
                $partyIds = Party::query()->where('type', $locked->value)->pluck('id');
                $ruleIds = AccountingRule::query()->where('party_type', $locked->value)->pluck('id');
                $headIds = TransactionHead::query()->whereIn('accounting_rule_id', $ruleIds)->pluck('id');
                $transactionIds = Transaction::query()->whereIn('transaction_head_id', $headIds)->pluck('id');

                $this->markTransactionsIncomplete($transactionIds);
                Party::query()->where('type', $locked->value)->update(['type' => null, 'is_active' => false]);
                AccountingRule::query()->where('party_type', $locked->value)->update(['party_type' => null, 'is_active' => false]);
                TransactionHead::query()->whereIn('accounting_rule_id', $ruleIds)->update(['is_active' => false]);
                AccountingOption::query()
                    ->forGroup(AccountingOption::GROUP_RULE_PARTY_TYPE)
                    ->where('value', $locked->value)
                    ->delete();

                $this->assertNoReference(Party::query()->where('type', $locked->value), 'party type');
                $this->assertNoReference(AccountingRule::query()->where('party_type', $locked->value), 'accounting rule party type');
                $this->assertNoReference(
                    AccountingOption::query()->forGroup(AccountingOption::GROUP_RULE_PARTY_TYPE)->where('value', $locked->value),
                    'mirrored accounting rule party type'
                );
                $this->assertInactive(Party::class, $partyIds, 'parties');
                $this->assertInactive(AccountingRule::class, $ruleIds, 'accounting rules');
                $this->assertInactive(TransactionHead::class, $headIds, 'transaction heads');
                $this->assertTransactionsIncomplete($transactionIds);
            }

            if ($locked->option_group === AccountingOption::GROUP_MONEY_ACCOUNT_KIND) {
                $moneyAccountIds = MoneyAccount::query()->where('kind', $locked->value)->pluck('id');
                MoneyAccount::query()->where('kind', $locked->value)->update(['kind' => null, 'is_active' => false]);
                $this->assertNoReference(MoneyAccount::query()->where('kind', $locked->value), 'money account type');
                $this->assertInactive(MoneyAccount::class, $moneyAccountIds, 'money accounts');
            }

            if ($locked->option_group === AccountingOption::GROUP_TRANSACTION_CATEGORY) {
                $ruleIds = AccountingRule::query()->where('category', $locked->value)->pluck('id');
                $headIds = TransactionHead::query()
                    ->where('category', $locked->value)
                    ->orWhereIn('accounting_rule_id', $ruleIds)
                    ->pluck('id');
                $sequenceIds = DocumentSequence::query()->where('category', $locked->value)->pluck('id');
                $transactionIds = Transaction::query()
                    ->where('category', $locked->value)
                    ->orWhereIn('transaction_head_id', $headIds)
                    ->pluck('id');

                $this->markTransactionsIncomplete($transactionIds);
                Transaction::query()->whereIn('id', $transactionIds)->update([
                    'category' => null,
                    'transaction_head_id' => null,
                ]);
                AccountingRule::query()->where('category', $locked->value)->update([
                    'category' => null,
                    'is_active' => false,
                ]);
                TransactionHead::query()->whereIn('id', $headIds)->update([
                    'category' => null,
                    'is_active' => false,
                ]);
                DocumentSequence::query()->where('category', $locked->value)->update([
                    'category' => null,
                    'is_active' => false,
                ]);

                $this->assertNoReference(AccountingRule::query()->where('category', $locked->value), 'accounting rule category');
                $this->assertNoReference(TransactionHead::query()->where('category', $locked->value), 'transaction head category');
                $this->assertNoReference(Transaction::query()->where('category', $locked->value), 'transaction category');
                $this->assertNoReference(DocumentSequence::query()->where('category', $locked->value), 'voucher numbering category');
                $this->assertNoReference(
                    TransactionHead::query()->whereKey($headIds->all())->whereNotNull('category'),
                    'transaction head category'
                );
                $this->assertNoReference(
                    Transaction::query()->whereKey($transactionIds->all())->whereNotNull('transaction_head_id'),
                    'transaction head dependency'
                );
                $this->assertNoReference(
                    DocumentSequence::query()->whereKey($sequenceIds->all())->whereNotNull('category'),
                    'voucher numbering category dependency'
                );
                $this->assertInactive(AccountingRule::class, $ruleIds, 'accounting rules');
                $this->assertInactive(TransactionHead::class, $headIds, 'transaction heads');
                $this->assertInactive(DocumentSequence::class, $sequenceIds, 'voucher numbering records');
                $this->assertTransactionsIncomplete($transactionIds);
            }

            $locked->delete();
            $this->assertDeleted(AccountingOption::class, $locked->id);
        }, attempts: 5);
    }

    public function voucherSequence(DocumentSequence $sequence): void
    {
        DB::transaction(function () use ($sequence): void {
            $locked = DocumentSequence::query()->lockForUpdate()->findOrFail($sequence->id);
            $locked->delete();
            $this->assertDeleted(DocumentSequence::class, $locked->id);
        }, attempts: 5);
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

    private function assertNoReference(Builder $query, string $relationship): void
    {
        if ($query->exists()) {
            throw new \RuntimeException('Safe deletion failed to clear the '.$relationship.' relationship.');
        }
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
    private function assertInactive(string $modelClass, Collection $ids, string $label): void
    {
        $recordIds = $ids->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($recordIds->isNotEmpty() && $modelClass::query()->whereKey($recordIds->all())->where('is_active', true)->exists()) {
            throw new \RuntimeException('Safe deletion failed to deactivate dependent '.$label.'.');
        }
    }

    /** @param Collection<int, int|string> $transactionIds */
    private function assertTransactionsIncomplete(Collection $transactionIds): void
    {
        $ids = $transactionIds->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        if (Transaction::query()->whereKey($ids->all())->where('status', '!=', 'incomplete')->exists()) {
            throw new \RuntimeException('Safe deletion failed to mark dependent transactions as incomplete.');
        }

        if (JournalEntry::query()->whereIn('transaction_id', $ids)->where('status', '!=', 'incomplete')->exists()) {
            throw new \RuntimeException('Safe deletion failed to mark dependent journal entries as incomplete.');
        }
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
    private function assertDeleted(string $modelClass, int $id): void
    {
        if ($modelClass::query()->whereKey($id)->exists()) {
            throw new \RuntimeException('Database deletion verification failed for '.$modelClass.' #'.$id.'.');
        }
    }

    /** @param Collection<int, int|string> $transactionIds */
    private function markTransactionsIncomplete(Collection $transactionIds): void
    {
        $ids = $transactionIds->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        Transaction::query()->whereIn('id', $ids)->update(['status' => 'incomplete']);
        JournalEntry::query()->whereIn('transaction_id', $ids)->update(['status' => 'incomplete']);
    }
}
