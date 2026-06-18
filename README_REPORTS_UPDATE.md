# Balance Sheet, Income Statement and Due Report Update

This update adds three simple accounting reports inspired by Finnacco's reporting flow but kept intentionally lightweight for the current HisebGhor MVP.

## Added routes

- `/reports/balance-sheet` (`reports.balance-sheet`)
- `/reports/income-statement` (`reports.income-statement`)
- `/reports/due-report` (`reports.due-report`)

## Added files

- `app/Http/Controllers/Accounting/Reports/FinancialReportController.php`
- `app/Services/Accounting/Reports/FinancialReportService.php`
- `resources/views/reports/balance-sheet.blade.php`
- `resources/views/reports/income-statement.blade.php`
- `resources/views/reports/due-report.blade.php`
- `resources/views/reports/partials/financial-rows.blade.php`
- `resources/views/components/reports/partials/filter-toolbar.blade.php`

## Logic summary

### Balance Sheet

- Uses posted journal entries only.
- Uses COA type `Asset`, `Liability`, and `Equity`.
- Includes money account opening balances.
- Includes party receivable/payable opening balances.
- Adds retained profit/loss from income and expense accounts up to the selected date.
- Shows whether Assets match Liabilities + Equity + Retained Profit.

### Income Statement

- Uses posted journal entries only.
- Uses COA type `Income` and `Expense`.
- Supports from/to date range.
- Calculates total income, total expense, and net profit/loss.

### Due Report

- Uses party receivable and payable COA mappings.
- Uses posted journal lines only.
- Shows receivable and payable rows separately.
- Calculates opening, debit movement, credit movement, closing due, and aging buckets.
- Aging uses simple FIFO settlement: payments reduce oldest due first.

## Export and print

Each report has:

- Generate filter button
- Reset
- Export CSV
- Print

## Permissions

- Balance Sheet and Income Statement use `statements.view`.
- Due Report uses `balances.view`.

## Deployment

```bash
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
