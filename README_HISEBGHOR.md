# HisebGhor Laravel Conversion — Stage 1

This stage converts the uploaded browser-local-storage prototype into a database-backed Laravel 13 application while keeping the same workflow and visual design.

## Implemented pages

- Dashboard
- Transaction Entry
  - Sales
  - Payment
  - Liability
  - Server-generated accounting-rule details
  - Server-generated automatic journal preview
  - Database-backed transaction posting

The remaining sidebar modules are displayed but intentionally disabled until their individual MVC modules are implemented.

## Architecture added

### Models

- Company
- ChartOfAccount
- MoneyAccount
- Party
- AccountingRule
- TransactionHead
- DocumentSequence
- Transaction
- JournalEntry
- JournalLine

### Controllers

- `App\Http\Controllers\Accounting\DashboardController`
- `App\Http\Controllers\Accounting\TransactionEntryController`

### Request validation

- `App\Http\Requests\Accounting\StoreTransactionRequest`

### Accounting services

- `AccountResolver`
- `JournalBuilder`
- `VoucherNumberService`
- `DecimalAmount`
- `TransactionPostingService`
- `DashboardService`

### Page-separated frontend

- Dashboard view: `resources/views/dashboard/index.blade.php`
- Transaction view: `resources/views/transactions/create.blade.php`
- Preview partials: `resources/views/transactions/partials`
- Accounting layout: `resources/views/layouts/accounting.blade.php`
- Sidebar partial: `resources/views/partials/accounting/sidebar.blade.php`
- Transaction JavaScript: `resources/js/pages/transaction-entry.js`
- HisebGhor CSS: `resources/css/pages/hisebghor.css`

## Local setup on macOS

Create the database:

```sql
CREATE DATABASE hisebghor
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Prepare Laravel:

```bash
cp .env.example .env
php artisan key:generate
```

Update the MySQL username and password in `.env`, then run:

```bash
composer install
npm install --ignore-scripts
php artisan migrate:fresh --seed
npm run build
composer run dev
```

Open:

```text
http://localhost:8000
```

Demo login:

```text
Email: admin@hisebghor.test
Password: password
```

## Seeded accounting flow

The demo seeder creates the same sample data as the HTML prototype:

- 15 chart-of-account ledgers
- 3 money accounts
- 6 parties
- 7 accounting rules
- 9 transaction heads
- 7 posted sample transactions
- Balanced journal entries and journal lines

## Posting protections

- Every accounting save is wrapped in a database transaction.
- Voucher sequence rows are locked while issuing a voucher number.
- A request UUID prevents repeated browser/network submissions.
- The same token is rechecked after the sequence lock.
- Debit and credit accounts are resolved only by Laravel services.
- Debit and credit lines are saved together.
- Company ownership is checked in controllers, validation rules, and services.

## Validation performed in the generated project

- All PHP files pass `php -l` syntax validation.
- All Blade templates compile.
- Laravel routes register correctly.
- `npm run build` completes successfully.

The build environment used for packaging did not include PHP DOM/XML, mbstring, or PDO SQLite extensions, so PHPUnit and database migrations could not be executed there. Run `php artisan test` on the macOS Homebrew PHP environment after configuring MySQL.
