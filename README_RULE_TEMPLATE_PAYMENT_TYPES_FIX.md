# Rule Template Payment Types Fix

## Fixed

The **Add Rule Template** form no longer limits or locks the Payment Type field when **Expense** is selected.

All three payment types are always shown and selectable for every transaction type:

- Paid/received in full
- Fully due
- Part paid, remaining due

## Technical changes

- Removed frontend filtering that hid and disabled payment-type options.
- The Rule Template page now uses the canonical system payment-type list instead of depending on possibly incomplete cloud master records.
- Rule Template validation accepts all three canonical payment types.
- Added a corrective migration that restores and activates missing payment types and updates transaction-type metadata.
- Rebuilt the production Vite assets.

## Deployment

```bash
cd /var/www/hisebghor
php artisan down
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo chown -R www-data:www-data /var/www/hisebghor
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 755 public/build
sudo systemctl restart php8.4-fpm
sudo nginx -t && sudo systemctl reload nginx
php artisan up
```

Hard-refresh the browser after deployment.
