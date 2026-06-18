# HisebGhor Dashboard Template Update

The accounting dashboard has been rebuilt to match the supplied Business Health Dashboard template while retaining the existing Laravel routes and accounting workflow.

## Updated areas

- Full template-style dashboard layout and responsive design
- Period filter: month, today, week, and quarter
- Available money from mapped money-account COA balances
- Sales, income, expense, and profit from posted transactions/journals
- Customer receivable and supplier/lender payable summaries
- Journal debit/credit balance health checks
- Recent transaction journal preview
- Money-account cards with live balances
- Sales settlement status derived from the configured accounting rule
- Six-month sales-versus-expense trend
- Financial statement snapshot and owner decision cards
- Template-style grouped sidebar navigation
- Print layout
- Existing sample-data reset retained in Dashboard Data Sources

## Main files changed

- `app/Services/Dashboard/DashboardService.php`
- `app/Http/Controllers/Accounting/DashboardController.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/layouts/accounting.blade.php`
- `resources/views/partials/accounting/sidebar.blade.php`
- `resources/views/balances/index.blade.php`
- `resources/css/pages/hisebghor.css`
- `tests/Feature/DashboardTest.php`
- `public/build/*`

## Validation completed

- PHP syntax validation completed for project PHP files
- All Blade views compiled and syntax-checked
- Vite production build completed

The current execution container does not include the DOM, XML, mbstring, or database PDO extensions required by PHPUnit, so the automated PHPUnit suite must be run in the normal project environment.
