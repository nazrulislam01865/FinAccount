# HisebGhor Feed Inventory & Business Tracking

This update converts the supplied browser-only prototype into database-backed Laravel modules integrated with the existing accounting engine.

## Added modules

### Inventory sidebar menu

- Inventory Position
- Stock Ledger
- Stock Adjustment
- Inventory Setup
  - Feed items
  - Categories and brands
  - Units and item-level pack conversion
  - Warehouses
  - Opening stock

### Business Tracking sidebar menu

- Business areas such as Cattle, Fish and Vegetables
- Operational units such as sheds, ponds, crops and plots
- Optional production cycles, seasons and batches
- Single-business, mixed-by-line and shared-percentage transaction allocation
- Company-level tracking rules

### Transaction Entry

Two real transaction categories are added:

- `FEED_PURCHASE`
- `FEED_SALE`

Both appear inside the existing Transaction Entry screen and reuse the existing party, money-account, partial-payment, credit, attachment, voucher and journal systems.

## Posting behavior

### Feed Purchase

Full payment:

```text
Dr Feed Inventory
    Cr Selected Cash / Bank / Mobile Account
```

Credit purchase:

```text
Dr Feed Inventory
    Cr Supplier Payable
```

Partial purchase:

```text
Dr Feed Inventory
    Cr Selected Money Account
    Cr Supplier Payable
```

A posted purchase also:

- creates an inventory document and item lines;
- converts the selected unit to the item's base unit;
- increases the selected warehouse balance;
- allocates transport and other purchase cost by item value or quantity;
- recalculates weighted-average inventory cost;
- creates stock-ledger movements;
- stores business allocations.

### Feed Sale

Full collection:

```text
Dr Selected Cash / Bank / Mobile Account
    Cr Feed Sales

Dr Feed Cost of Goods Sold
    Cr Feed Inventory
```

Credit and partial sales use Customer Receivable for the unpaid amount. The COGS and inventory lines are appended to the same journal entry as the revenue lines.

A posted sale also:

- locks warehouse balances before checking stock;
- blocks overselling;
- reduces stock using base-unit quantity;
- calculates COGS from the current weighted-average cost;
- creates a sales invoice;
- creates stock-ledger movements;
- stores business allocations.

## Database migrations

- `2026_07_13_000500_create_inventory_and_business_tracking_tables.php`
- `2026_07_13_000510_seed_feed_inventory_and_tracking_setup.php`

The second migration configures all existing companies with:

- Feed Purchase and Feed Sale transaction categories;
- accounting rules for full, partial and credit settlement;
- transaction heads and voucher numbering;
- inventory, sales, COGS and adjustment account mappings;
- default business areas, operational units and cycles;
- categories, brands, KG/Bag units, warehouses and starter feed items.

It preserves existing transaction and COA IDs.

## Deployment

Back up the database before deployment.

```bash
php artisan down

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

The production frontend bundle is already included. Rebuild it when source assets are changed:

```bash
npm ci
npm run build
```

## First-use flow

1. Open **Business Tracking** and review Cattle, Fish and Vegetables, then add or edit sheds, ponds, crops and cycles.
2. Open **Inventory → Inventory Setup** and review categories, brands, units, warehouses and feed items.
3. Post existing quantity through **Opening Stock**.
4. Open **Transaction Entry → Feed Purchase** to buy stock.
5. Open **Transaction Entry → Feed Sale** to sell stock and automatically post sales and COGS journals.
6. Review **Inventory Position**, **Stock Ledger**, the normal **Transaction Register**, and **Journal Entries**.

## Audit protection

Posted Feed Purchase and Feed Sale records cannot be directly edited or deleted because they are linked to stock balances, stock movements and accounting journals. Use a stock adjustment or an explicit reversal workflow instead.

Core inventory accounting accounts are protected from safe deletion until Inventory Setup is mapped to replacements.

## Validation performed

- PHP syntax check across application, migrations, routes and tests
- Blade compilation for all changed transaction, inventory, business-tracking and sidebar views
- Laravel route registration verification
- Vite production build
- Added feature tests for purchase valuation, sale COGS and insufficient-stock rejection

PHPUnit could not be executed in the build container because its PHP installation does not include `dom`, `mbstring`, `xml`, `xmlwriter`, or a database PDO driver. Run the test suite in the normal deployment/development PHP environment.
