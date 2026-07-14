<?php

namespace App\Services\Accounting\SafeDelete;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedBusinessTrackingUnit;
use App\Models\Feed\FeedWarehouse;

class SafeDeleteService
{
    public function __construct(
        private readonly DependencyInspector $inspector,
        private readonly DependencyDetacher $detacher,
    ) {}

    public function inspectChartOfAccount(ChartOfAccount $record): DeletionPlan { return $this->inspector->chartOfAccount($record); }
    public function inspectMoneyAccount(MoneyAccount $record): DeletionPlan { return $this->inspector->moneyAccount($record); }
    public function inspectParty(Party $record): DeletionPlan { return $this->inspector->party($record); }
    public function inspectAccountingRule(AccountingRule $record): DeletionPlan { return $this->inspector->accountingRule($record); }
    public function inspectTransactionHead(TransactionHead $record): DeletionPlan { return $this->inspector->transactionHead($record); }
    public function inspectTransaction(Transaction $record): DeletionPlan { return $this->inspector->transaction($record); }
    public function inspectAccountingOption(AccountingOption $record): DeletionPlan { return $this->inspector->accountingOption($record); }
    public function inspectVoucherSequence(DocumentSequence $record): DeletionPlan { return $this->inspector->voucherSequence($record); }
    public function inspectFeedItem(FeedItem $record): DeletionPlan { return $this->inspector->feedItem($record); }
    public function inspectFeedBusinessTrackingUnit(FeedBusinessTrackingUnit $record): DeletionPlan { return $this->inspector->feedBusinessTrackingUnit($record); }
    public function inspectFeedWarehouse(FeedWarehouse $record): DeletionPlan { return $this->inspector->feedWarehouse($record); }

    public function deleteChartOfAccount(ChartOfAccount $record): void { $this->detacher->chartOfAccount($record); }
    public function deleteMoneyAccount(MoneyAccount $record): void { $this->detacher->moneyAccount($record); }
    public function deleteParty(Party $record): void { $this->detacher->party($record); }
    public function deleteAccountingRule(AccountingRule $record): void { $this->detacher->accountingRule($record); }
    public function deleteTransactionHead(TransactionHead $record): void { $this->detacher->transactionHead($record); }
    public function deleteAccountingOption(AccountingOption $record): void { $this->detacher->accountingOption($record); }
    public function deleteVoucherSequence(DocumentSequence $record): void { $this->detacher->voucherSequence($record); }
    public function deleteFeedItem(FeedItem $record): void { $this->detacher->feedItem($record); }
    public function deleteFeedBusinessTrackingUnit(FeedBusinessTrackingUnit $record): void { $this->detacher->feedBusinessTrackingUnit($record); }
    public function deleteFeedWarehouse(FeedWarehouse $record): void { $this->detacher->feedWarehouse($record); }
}
