# HisebGhor Automatic Code Generation Update

## Implemented

- Chart of Accounts codes are now generated automatically by account type:
  - Asset: starts at `1000`
  - Liability: starts at `2000`
  - Income: starts at `3000`
  - Expense: starts at `4000`
  - Equity: starts at `5000`
- Each new COA uses the highest existing numeric code in its type series plus one.
- Changing an existing COA to another account type assigns the next code from the new series.
- Accounting Rule Template codes are generated from the Name field and made unique automatically.
- Transaction Head codes are generated from the Head Name and made unique automatically.
- Party Type Initial Value is generated from the Display Label, for example `Customer` becomes `C` and `Sales Agent` becomes `SA`.
- Money Account Type Initial Value is generated from the Display Label in the same way.
- Party codes are generated per Party Type, for example `Customer` starts from `C-001`, then `C-002`.
- Changing a party to another Party Type assigns the next code in that type's series.
- The Transaction Types page now has an Add option.
- Custom Transaction Types include a required Transaction Direction (`Money In` or `Money Out`) so automatic accounting rule templates can be generated correctly.
- All generated fields are read-only in the forms. The backend generates the final value again while saving, so codes remain safe and unique even if two users open the same form.

## Deployment

No new database migration is required for this update.

```bash
cd /var/www/hisebghor

php artisan down
php artisan optimize:clear

sudo chown -R www-data:www-data /var/www/hisebghor
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 755 public/build

sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart

sudo systemctl restart php8.4-fpm
sudo nginx -t
sudo systemctl reload nginx

php artisan up
```

The updated production Vite build is already included in `public/build`.
