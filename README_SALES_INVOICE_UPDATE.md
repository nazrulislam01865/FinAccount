# HisebGhor Sales Invoice Update

This update adds sales invoice generation from posted sales transactions while keeping accounting records controlled by journal entries and journal lines.

## Main behavior

- Accounting Rules now include `Generate sales invoice` and `Invoice title`.
- Only rules in the `Sales` transaction category can generate invoices.
- When a posted transaction uses a sales rule with invoice generation enabled, the system creates or updates one linked `sales_invoices` record.
- The invoice does not post accounting entries. Ledgers, trial balance, due report, and statements continue to come from `journal_lines`.
- Transaction Register shows an `Invoice` button for generated invoices.
- Existing sales transactions can use `Generate Invoice` if their accounting rule has invoice generation enabled.
- Invoice page is print-ready; use the Print / Save PDF button to print or save as PDF from the browser.

## Accounting examples

Cash sale invoice:

```text
Dr Money Account
    Cr Sales Income
Invoice: Paid
```

Credit sale invoice:

```text
Dr Customer Receivable
    Cr Sales Income
Invoice: Unpaid
```

Partial sale invoice:

```text
Dr Money Account          Paid Amount
Dr Customer Receivable    Due Amount
    Cr Sales Income       Total Amount
Invoice: Partial
```

## Role Matrix UI fix

The Role Matrix page no longer uses a fixed 68vh internal table height. The page now flows normally like the other pages, so the footer does not leave a large blank section after it.

## Run after deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan route:clear
npm install
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If you do not run `npm run build`, the included existing build assets were also patched, but building fresh is still recommended.
