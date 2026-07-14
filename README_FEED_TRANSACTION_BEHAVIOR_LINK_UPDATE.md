# Feed Purchase / Feed Sale Transaction Behaviour Link Update

## What changed

Feed Purchase and Feed Sale now use one posting path for accounting and inventory:

- Normal Transaction Entry with **Sale + Others** stays accounting-only.
- Normal Transaction Entry with **Sale + Fish / Cattle / Vegetables / custom Business Area** is routed to `FeedPostingService::postSale()`.
- Dedicated **Feed Purchase** page continues to use `FeedPostingService::postPurchase()`.
- Dedicated **Feed Sale** page continues to use `FeedPostingService::postSale()`.
- `FeedPostingService` posts the accounting transaction and updates feed inventory in the same database transaction.
- Feed Sale also appends the automatic COGS journal: Debit Feed COGS, Credit Feed Inventory.

## Backend files changed

- `app/Http/Controllers/Accounting/TransactionEntryController.php`
- `app/Http/Requests/Accounting/StoreTransactionRequest.php`
- `app/Services/Feed/FeedPostingService.php`
- `app/Services/Feed/FeedLedgerPostingService.php`
- `app/Services/Feed/FeedAccountingSetupService.php`
- `app/Services/Accounting/TransactionPostingService.php`
- `app/Services/Accounting/TransactionUpdateService.php`
- `resources/views/transactions/create.blade.php`
- `resources/js/pages/transaction-entry.js`
- `database/migrations/2026_07_14_210122_replace_warehouse_with_tracking_unit.php`

## Required setup: COA

Create or keep these Level 3 accounts active:

1. **Feed Inventory**
   - Type: Asset
   - Normal balance: Debit
   - Used by: Feed Purchase and Feed Sale stock/COGS posting

2. **Feed Sales**
   - Type: Income
   - Normal balance: Credit
   - Used by: Feed Sale income posting

3. **Feed Cost of Goods Sold**
   - Type: Expense
   - Normal balance: Debit
   - Used by: automatic Feed Sale COGS posting

The system can auto-create system accounts if Feed Setup is opened and the setup is missing.

## Required setup: Transaction Heads

Create or keep these heads active:

1. **Feed Purchase**
   - Transaction Type / Category: Purchase
   - Party Type: Supplier
   - Posting Account: Feed Inventory
   - Allowed settlements: Cash, Credit, Partial

2. **Feed Sale**
   - Transaction Type / Category: Sale
   - Party Type: Customer
   - Posting Account: Feed Sales
   - Allowed settlements: Cash, Credit, Partial

The selected heads must be saved in `feed_settings` as:

- `purchase_transaction_head_id`
- `sale_transaction_head_id`
- `cogs_account_id`
- `default_tracking_unit_id` (this is the Feed Setup warehouse/location id)

## Required setup: Accounting Rules

For **Feed Purchase** head:

1. Fully paid purchase
   - Debit: Transaction Head COA = Feed Inventory
   - Credit: Selected Money Account
   - Party: Supplier
   - Money Account: Required

2. Fully due purchase
   - Debit: Transaction Head COA = Feed Inventory
   - Credit: Party Payable COA
   - Party: Supplier
   - Money Account: Not required

3. Partial purchase
   - Debit: Transaction Head COA = Feed Inventory
   - Credit: Selected Money Account for paid amount
   - Credit: Party Payable COA for due amount
   - Party: Supplier
   - Money Account: Required

For **Feed Sale** head:

1. Fully received sale
   - Debit: Selected Money Account
   - Credit: Transaction Head COA = Feed Sales
   - Party: Customer
   - Money Account: Required

2. Fully due sale
   - Debit: Party Receivable COA
   - Credit: Transaction Head COA = Feed Sales
   - Party: Customer
   - Money Account: Not required

3. Partial sale
   - Debit: Selected Money Account for received amount
   - Debit: Party Receivable COA for due amount
   - Credit: Transaction Head COA = Feed Sales
   - Party: Customer
   - Money Account: Required

Automatic extra COGS entry on every Feed Sale:

- Debit: Feed Cost of Goods Sold
- Credit: Feed Inventory

This entry is not configured as a normal accounting rule because it depends on inventory average cost.

## Inventory effect

Feed Purchase:

- Creates a feed document.
- Creates feed document lines.
- Increases stock balance by warehouse/location and item.
- Recalculates weighted average cost.
- Creates stock movement records.
- Creates accounting journal.

Feed Sale:

- Checks stock availability before posting.
- Creates a feed document.
- Creates feed document lines.
- Decreases stock balance by warehouse/location and item.
- Creates stock movement records.
- Creates sales journal.
- Creates COGS journal.

## Important rule

`tracking_unit_id` in feed inventory tables is intentionally used as the Feed Setup warehouse/location id. It does not point to business tracking units. Business Area is stored as the sale selling type, while inventory remains warehouse-based.
