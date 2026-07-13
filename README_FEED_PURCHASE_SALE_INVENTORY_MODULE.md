# Feed Purchase, Sale and Inventory Module

## What is included

- Separate **Feed Purchase** page.
- Separate **Feed Sale** page.
- **Feed Inventory** page with purchased quantity, sold quantity, on-hand stock, average cost, stock value and stock ledger.
- **Feed Setup** page for warehouses and feed items.
- Quick links from Transaction Entry and a dedicated Feed Business sidebar section.

## Direct accounting integration

Feed Setup no longer contains a manual **Accounting Connection** form. The feed module prepares and maintains its internal purchase and sale posting heads automatically and posts directly to the main accounting ledger.

Existing valid Feed Inventory, Feed Sales and Feed Cost of Goods Sold accounts are preserved when upgrading. When any required ledger is missing or incompatible, the application creates an active level-3 system ledger automatically.

The internal transaction heads use the reserved codes:

- `SYS-FEED-PUR` — Feed Purchase
- `SYS-FEED-SAL` — Feed Sale

They do not require Accounting Rules and are excluded from generic Transaction Entry so stock-changing transactions cannot be posted without the corresponding inventory movement.

### Feed Purchase journal

- Fully paid: Debit Feed Inventory; Credit selected Cash/Bank/Mobile Account.
- Partially paid: Debit Feed Inventory; Credit selected Cash/Bank/Mobile Account and Supplier Payable.
- Fully due: Debit Feed Inventory; Credit Supplier Payable.

Transport and other purchase costs are allocated by item value or quantity and included in weighted-average inventory cost.

### Feed Sale journal

- Fully received: Debit selected Cash/Bank/Mobile Account; Credit Feed Sales.
- Partially received: Debit selected Cash/Bank/Mobile Account and Customer Receivable; Credit Feed Sales.
- Fully due: Debit Customer Receivable; Credit Feed Sales.

Every feed sale also posts to the same journal entry:

- Debit Feed Cost of Goods Sold.
- Credit Feed Inventory.

COGS uses the warehouse item's weighted-average cost at the time of sale.

## Shared accounting behavior retained

Direct feed posting still uses the application's:

- Voucher numbering
- Financial-period validation
- Money accounts
- Supplier payable and customer receivable mappings
- Sales invoices
- Attachments
- Transaction Register
- Journal Entries
- Ledger and financial reports
- Safe transaction deletion and stock reversal

## Inventory behavior

- Purchase increases stock.
- Sale decreases stock.
- Bags are converted to KG using each item's pack size.
- Stock is maintained separately by item and warehouse.
- Sale posting is rejected when stock is insufficient.
- Every movement stores quantity and cost before and after posting.
- Deleting the newest feed transaction restores the previous stock balance and average cost.
- A feed transaction with later dependent stock movements cannot be deleted until the newer movements are removed.
- Feed transactions cannot be edited through generic Transaction Entry because stock and COGS must remain synchronized.

## Required setup

1. Ensure Suppliers have payable accounts for fully due or partially paid purchases.
2. Ensure Customers have receivable accounts for fully due or partially received sales.
3. Add at least one active warehouse in Feed Setup and choose the default warehouse when needed.
4. Add feed items.
5. Record purchases and sales directly from the Feed Purchase and Feed Sale pages.

No manual Transaction Head, Accounting Rule, Feed Inventory, Feed Sales or COGS connection is required.

## Deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Rebuild production assets only when frontend source files are changed:

```bash
npm install
npm run build
```
