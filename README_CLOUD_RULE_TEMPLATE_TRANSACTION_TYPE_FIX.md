# Cloud Rule Template Transaction Type Fix

## Symptom

The Rule Template list showed `EXPENSE`, but the Edit Rule Template modal displayed a blank Transaction Type field.

## Root cause

The list reads the saved `accounting_rules.category` value directly. The modal dropdown is built only from active `accounting_options` rows in the `transaction_category` group. On the cloud database, the core `EXPENSE` option was missing or inactive, so the browser could not select the saved `EXPENSE` value.

## Fix

Migration `2026_06_25_230000_restore_core_transaction_types_for_rule_templates.php` restores every core transaction type and all three payment types, marks them active, and restores their current metadata. Existing rule templates are not deleted or recreated.

## Deploy

```bash
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```
