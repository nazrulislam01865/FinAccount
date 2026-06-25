# All Payment Types for Every Transaction Head

## Fixed issue

The Transaction Head form previously hid payment types according to the selected transaction type. For example, Customer Collection, Supplier Payment, Owner, and Loan transaction types showed only `Paid/received in full`.

The form now always displays all three choices for every transaction type:

- Paid/received in full
- Fully due
- Part paid, remaining due

The user can select one, two, or all three options according to the transaction head being configured. A new transaction head starts with only `Paid/received in full` selected, while the other options remain visible and selectable.

## Backend support

The backend now accepts every active payment type for every transaction type. Automatic accounting-rule templates are also available for all transaction type and payment type combinations, including cash, fully due, and partial settlement.

Existing transaction-head selections are preserved. The migration does not force all three options onto existing heads; it only makes every option available so the user can edit each head and select what is needed.

For collection/payment-style heads, a fully due or partial rule may use both the head COA and the party receivable/payable COA. Those mappings must resolve to different COA accounts for a non-zero journal; the existing journal validation will show a clear setup error when the same COA is used on both sides.

## Important files

```text
app/Support/TransactionTypes.php
app/Services/Accounting/TransactionHeadService.php
app/Services/Accounting/AccountingRuleService.php
resources/views/transaction-heads/index.blade.php
resources/js/pages/transaction-head.js
database/seeders/AccountingOptionSeeder.php
database/migrations/2026_06_25_220000_allow_all_payment_types_for_transaction_heads.php
tests/Feature/Accounting/AccountingTemplateParityTest.php
```

## Deployment

The updated Vite production build is included in `public/build`, so no server-side npm build is required.

```bash
cd /var/www/hisebghor

php artisan down
php artisan optimize:clear
php artisan migrate --force

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

## Validation completed

- All 229 PHP files passed syntax validation.
- All 33 transaction type/payment type rule-template combinations were generated successfully in a direct template check.
- Vite production assets built successfully.
- Laravel loaded 115 application routes.

The full PHPUnit suite could not run in the packaging environment because the PHP CLI is missing DOM, mbstring, XML, XMLWriter, and a PDO database driver. Run `php artisan test` in the normal local/server PHP environment.
