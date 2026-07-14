# Feed Transaction Warehouse + Accounting Fix

This update fixes the Transaction Entry Sale feed mode warehouse dropdown and confirms the backend feed accounting/inventory posting path.

## Fixed

- Transaction Entry -> Sale -> What are you selling now loads active Business Areas from Feed Business Area Master Data, plus the default Others option.
- When a Business Area is selected, the feed sale form opens.
- Warehouse / Location dropdown now loads all active records from Feed Setup -> Warehouses and is no longer incorrectly filtered by Business Area.
- Default warehouse is selected from Feed Settings, or the first active Feed Warehouse if the configured default is missing.
- The dropdown help text now correctly points to Feed Setup -> Warehouses / Locations.
- Feed Purchase posting no longer fails when `cost_allocation` is absent; it defaults to value allocation.
- Store validation now allows the configured internal Feed Sale head when a feed sale is posted from Transaction Entry.

## Backend behavior checked

- Feed Purchase posts through `FeedPostingService::postPurchase()` and creates accounting transaction + journal + feed document + feed lines + stock increase + stock movements.
- Feed Sale posts through `FeedPostingService::postSale()` and creates accounting transaction + journal + feed document + feed lines + stock decrease + stock movements + COGS journal.
- Sale + Others still uses normal `TransactionPostingService` only and does not affect inventory.

## Run after upload

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
```
