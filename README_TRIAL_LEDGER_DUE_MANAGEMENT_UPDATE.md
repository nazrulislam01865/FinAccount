# Trial Balance, Ledger Report, and Due Management Update

This update adds three simple accounting-safe pages to HisebGhor. Finnacco was used only as a reference for the reporting idea; the implementation follows the current HisebGhor journal, party, money account, and transaction-head structure.

## Added pages

- Trial Balance: `/reports/trial-balance`
- Ledger Report: `/reports/ledger-report`
- Due Management: `/reports/due-management`

## Logic

### Trial Balance

- Reads opening balances from money accounts and party mappings.
- Reads only posted journal entries.
- Calculates opening debit/credit before the selected date range.
- Calculates period debit/credit inside the date range.
- Calculates closing debit/credit.
- Shows difference so the user can verify whether the books are balanced.

### Ledger Report

- Requires one account selection.
- Optional party filter for party-control accounts.
- Reads only posted journal lines for the selected account.
- Shows opening balance, voucher movement, debit, credit, and running balance.

### Due Management

- Uses the same party receivable/payable logic as Due Report.
- Shows outstanding receivable and payable dues.
- Settlement does not directly edit due balances.
- Settlement posts a normal transaction through existing TransactionPostingService.
- The selected Transaction Head must use an Accounting Rule that decreases the correct due account:
  - Receivable settlement: Debit money account, Credit party receivable.
  - Payable settlement: Debit party payable, Credit money account.
- Amount cannot be greater than current outstanding due.

## Permissions

- Trial Balance: `statements.view`
- Ledger Report: `balances.view`
- Due Management view: `balances.view`
- Due Management settlement: `balances.view` + `transactions.manage`

## Main files changed

- `routes/web.php`
- `app/Services/Accounting/Reports/FinancialReportService.php`
- `app/Http/Controllers/Accounting/Reports/FinancialReportController.php`
- `app/Http/Controllers/Accounting/DueManagementController.php`
- `app/Models/JournalLine.php`
- `resources/views/reports/trial-balance.blade.php`
- `resources/views/reports/ledger-report.blade.php`
- `resources/views/reports/due-management.blade.php`
- `resources/views/partials/accounting/sidebar.blade.php`
- `resources/css/pages/hisebghor.css`

## Deployment

No new migration is required for these reports.

```bash
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
