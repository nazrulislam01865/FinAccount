# HisebGhor — Template-Matched Database Accounting Backend

This build matches the supplied HisebGhor Sales, Payment & Liability template across all eleven accounting pages and moves the prototype's browser data and posting behavior into Laravel, MySQL, controllers, services, models, Form Requests, migrations, and tests.

## Complete page list

1. Dashboard
2. Transaction Entry
3. Transaction Register
4. Chart of Accounts
5. Money Accounts
6. Parties
7. Accounting Rules
8. Transaction Heads
9. Journal Entries
10. Balances
11. Basic Statements

## Important implementation points

- All accounting dropdown values come from the `accounting_options` database table.
- Setup values are validated against active database options; edited HTTP requests cannot inject unknown static values.
- Transaction heads call linked accounting rules; users never choose debit or credit.
- Journals, balances, dashboard metrics, and statements come from posted database records.
- Every read/write is scoped to the logged-in user's company.
- Transaction posting is idempotent and voucher generation is concurrency-safe.
- Transaction edits rebuild journal lines; transaction deletes remove their derived journals.
- Used setup records are protected from unsafe deletion.
- The demo seeder reproduces the exact template records and totals.
- Secondary supplied sources are used only for reusable UI components, never for accounting backend logic.

See [`docs/TEMPLATE_BACKEND_PARITY_AUDIT.md`](docs/TEMPLATE_BACKEND_PARITY_AUDIT.md) for the complete field, rule, dataset, validation, and test audit.

## Fresh setup

```bash
composer install
npm ci
npm run build
php artisan migrate
php artisan db:seed --class=HisebGhorDemoSeeder
php artisan optimize:clear
```

## Existing production database

```bash
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The migration itself inserts the required accounting option records, so existing companies receive the database-backed dropdown domains without running the demo seeder.

## Tests

```bash
php artisan test
```

The main parity suite is `tests/Feature/Accounting/AccountingTemplateParityTest.php`.
