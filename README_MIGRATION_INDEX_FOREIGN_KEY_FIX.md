# Migration Fix: feed_stock_balance_unique cannot be dropped

The failed migration `2026_07_14_210122_replace_warehouse_with_tracking_unit.php` was dropping the `feed_stock_balance_unique` index while MySQL still needed that index for foreign-key support.

This version fixes the migration by:

- making the migration safe to re-run after a partial failure;
- adding support indexes before replacing unique indexes;
- dropping and recreating the affected foreign keys in the correct order;
- using the real table name `feed_stock_movements` instead of the non-existing `feed_movements`;
- also converting `feed_documents.warehouse_id` to `tracking_unit_id`, which the Feed Purchase/Sale code expects;
- keeping `tracking_unit_id` connected to `feed_warehouses`, because the Feed Setup / Feed Purchase / Feed Sale forms still use the warehouse table for locations.

After uploading, run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
