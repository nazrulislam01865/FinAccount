# HisebGhor Company Setup and Company Master Data

## Overview

This update adds a production-oriented Company Setup module inspired by the Finnacco reference workflow while retaining the existing HisebGhor visual system, accounting engine, Fleet-style role matrix, safe-delete behavior, dashboard, logout, branding, and footer.

The implementation is separated into migrations, Eloquent models, form requests, controllers, services, middleware, support classes, views, and tests. Company Setup is not a static profile page: its currency, time zone, financial year, status, and branding selections are used by the accounting system.

## Company Setup fields

- Company code (system-generated, read-only)
- Legal company name
- Short name
- Business Type
- Company status
- Trade License number
- BIN / VAT registration number
- TIN
- Currency
- Accounting method (Accrual, enforced because the current receivable/payable posting engine is accrual-based)
- Time Zone
- Current Financial Year
- Default branch / location
- Registered address
- Contact email
- Contact phone
- Website
- Company logo and favicon through the existing protected Branding Settings module

## New reusable master-data modules

The following missing company dependencies were added under **Configuration → Other Master Data**:

1. **Business Types**
   - Code, name, description, sort order, default and active status
   - Used directly by Company Setup

2. **Currencies**
   - ISO-style three-letter code, name, symbol, decimal precision, sort order, default and active status
   - Used throughout transaction inputs, balances, journals, dashboard cards, and statements
   - Decimal precision is limited to the accounting database precision (0–2 decimal places)
   - Decimal precision cannot be changed for the selected currency after transactions exist

3. **Time Zones**
   - Code, display name, UTC offset, PHP/IANA time-zone identifier, sort order, default and active status
   - Applied to authenticated company requests and date/time display context

4. **Financial Years**
   - Name, start date, end date, lock date, current flag, open/closed status and active status
   - Date ranges cannot overlap
   - A current year must be Active and Open
   - Date ranges cannot be changed after transactions exist within the year
   - A Financial Year containing transactions cannot be deleted

Existing Party Types, Money Account Types, Transaction Categories, and Voucher Numbering remain unchanged and continue to appear in Other Master Data.

## Accounting integration

- A company must be Active and Company Setup must be complete before posting or updating transactions.
- Transaction dates must fall inside an Active, Open Financial Year.
- Transactions dated on or before a Financial Year's lock date are rejected.
- Transaction amount validation and input steps use the selected Currency's decimal precision.
- Currency symbols/codes are used across Dashboard, Transaction Register, Journal Entries, Account Balances, Party Balances, Money Accounts, and Financial Statements.
- The selected company Time Zone is applied per authenticated request.
- Existing automatic accounting-rule, transaction-head, journal, and safe-delete logic remains intact.

## Permissions

The Fleet-style Role Matrix now includes separate View and Manage permissions for:

- Company Setup
- Business Types
- Currencies
- Time Zones
- Financial Years

Deletion additionally requires the existing **Delete Records** permission. Company branding remains protected by the existing Super Admin-only Branding Settings permission.

## Clean architecture

Main implementation locations:

- Migrations: `database/migrations/2026_06_17_000400_*` and `000410_*`
- Models: `BusinessType`, `Currency`, `TimeZone`, `FinancialYear`, expanded `Company`
- Requests: `app/Http/Requests/Company`
- Controllers: `app/Http/Controllers/Accounting/Company`
- Services: `app/Services/Company`
- Middleware: `ApplyCompanyTimeZone`
- Currency context: `App\Support\CompanyContext`
- Company Setup views: `resources/views/company-setup`
- Company master views: `resources/views/company-masters`
- Feature tests: `tests/Feature/Accounting/CompanySetupModuleTest.php`

## Deployment

Create Laravel runtime directories before any Artisan command:

```bash
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         bootstrap/cache

chmod -R u+rwX,g+rwX storage bootstrap/cache
find bootstrap/cache -type f ! -name '.gitignore' -delete
```

Then run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan storage:link || true

npm ci
npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

