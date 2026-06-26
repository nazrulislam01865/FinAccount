# Expense Transaction Type / Transaction Head Cloud Fix

## Problems fixed

1. Clicking the **Expense** tab on Transaction Entry could fall back to the first transaction type instead of opening the Expense entry form.
2. Editing an expense-related Transaction Head could show an empty required **Transaction Type** select.

## Root cause

Older MySQL data could retain the core transaction type value as `Expense`, while the current application uses the canonical internal value `EXPENSE`.

MySQL's usual case-insensitive collation allowed an older migration to find the `Expense` row when searching for `EXPENSE`, but that migration updated only its metadata and did not rewrite the stored `value`. This produced a mismatch:

- `accounting_options.value`: `Expense`
- `transaction_heads.category`: `EXPENSE`

The form and transaction-entry filters compare these internal codes, so the Expense option did not match the Expense heads.

## Changes

- Added canonical transaction-type normalization for core types.
- Added legacy database aliases so the page works even before the repair migration finishes.
- Canonicalized Transaction Head category values on read/write.
- Fixed Transaction Entry, preview, posting, update, filtering, and rule matching to understand legacy values.
- Updated the Transaction Head edit modal to apply and resync its transaction type immediately.
- Added migration:

  `2026_06_26_160000_normalize_core_transaction_type_values.php`

  It repairs mixed-case legacy core values in:

  - `accounting_options`
  - `transaction_heads`
  - `accounting_rules`
  - `document_sequences`
  - `transactions`

  It also ensures every system transaction type exists and is active.

## Deployment

The frontend build is already included in `public/build`; no Node command is required on the server.

```bash
cd /var/www/hisebghor

php artisan down

git fetch origin main
git pull --ff-only origin main

mkdir -p storage/framework/views \
         storage/framework/sessions \
         storage/framework/cache/data \
         storage/framework/testing \
         storage/logs \
         bootstrap/cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 755 public/build

sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart

sudo systemctl restart php8.4-fpm
sudo nginx -t
sudo systemctl reload nginx

sudo -u www-data php artisan up
```
