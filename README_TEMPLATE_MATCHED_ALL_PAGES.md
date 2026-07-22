# HisebGhor — Complete Template-Matched Accounting Pages

This build keeps the previously completed pages unchanged and adds the remaining pages from the supplied HTML template using Laravel, Blade, controllers, services, form requests, and MySQL.

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

## Architecture

- Every page has its own controller, service, and Blade view.
- Setup CRUD pages use dedicated Form Request validation classes.
- Money Accounts, Parties, Accounting Rules, and Transaction Heads reuse one shared Blade modal component and one small generic JavaScript module.
- Journal Entries, Balances, and Basic Statements are generated from posted database journals.
- All reads and writes are scoped to the logged-in user's company.
- Existing transaction posting, automatic debit/credit resolution, voucher numbering, and deletion logic remain intact.

## Build commands

```bash
composer install
npm install
npm run build
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=HisebGhorDemoSeeder
```

## Test command

```bash
php artisan test
```
