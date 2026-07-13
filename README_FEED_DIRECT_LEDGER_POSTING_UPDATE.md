# Feed Direct Ledger Posting Update

## Change summary

- Removed the Feed Setup **Accounting Connection** form and its POST route.
- Feed Setup now contains only feed item and warehouse management.
- Existing valid feed ledger accounts are preserved automatically.
- Missing Feed Inventory, Feed Sales and Feed COGS ledgers are created automatically.
- Dedicated internal Feed Purchase and Feed Sale transaction heads are created and maintained automatically.
- Feed posting no longer depends on Accounting Rules.
- Internal feed heads cannot be selected from generic Transaction Entry.

## Automatic journal rules

### Purchase

- Debit Feed Inventory for the full purchase value including allocated transport and other costs.
- Credit the selected Money Account for the amount paid now.
- Credit Supplier Payable for the remaining due amount.

### Sale

- Debit the selected Money Account for the amount received now.
- Debit Customer Receivable for the remaining due amount.
- Credit Feed Sales for the full sale amount.
- Debit Feed Cost of Goods Sold and Credit Feed Inventory using weighted-average cost.

Stock, feed documents, transactions, journal entries, invoices and attachments remain part of the same database posting workflow. A failure rolls the complete operation back.

## Compatibility

The update works with existing feed data. Existing valid account mappings are reused, while the previous visible transaction heads are replaced in `feed_settings` with dedicated internal heads so generic transactions cannot bypass stock posting.
