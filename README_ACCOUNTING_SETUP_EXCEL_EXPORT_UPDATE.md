# Accounting Setup Excel Export Update

## Added

- **Export Excel** action on:
  - Chart of Accounts
  - Transaction Heads
  - Accounting Rules
- Each export downloads all saved company records, not only the currently visible search/filter result.
- Export routes use the same view permission as their corresponding page.
- Manage-only users opening an add-only screen do not see an export action they cannot access.

## Workbook formatting

Every generated `.xlsx` file includes:

- Company name
- Export date/time
- Total record count
- Styled title and header rows
- Frozen header row
- Excel auto-filter
- Wrapped long text
- Alternating row backgrounds
- Useful column widths
- Landscape print setup

Draft rows and UI-only Action/checkbox columns are not exported.

## Technical notes

- The exporter is company-scoped.
- It preserves codes as text and writes all values as safe inline text, preventing spreadsheet formula injection.
- It creates real `.xlsx` workbooks without adding a Composer package or requiring PHP `ext-zip`.
- No database migration or frontend asset build is required.

## Deployment

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
