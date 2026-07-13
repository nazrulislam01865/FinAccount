# Accounting Setup Excel Corruption Fix

## Problem

Excel displayed a recovery warning after downloading Chart of Accounts, Transaction Heads, or Accounting Rules. In some Excel versions the recovered workbook opened without the exported rows.

## Root cause

The generated worksheet XML placed `mergeCells` before `autoFilter`. SpreadsheetML requires `autoFilter` to appear before `mergeCells`. ZIP readers could still open the package, but Microsoft Excel treated the worksheet part as structurally invalid and attempted to repair it.

The portable ZIP writer also converted CRC values through an unsigned decimal string. That is unnecessary and can produce an invalid CRC on 32-bit PHP builds.

## Fixes

- Writes worksheet elements in Excel-compatible SpreadsheetML order.
- Writes `autoFilter` before `mergeCells`.
- Adds `sheetPr/pageSetUpPr` for the landscape fit-to-page settings.
- Writes ZIP CRC values safely on both 32-bit and 64-bit PHP.
- Keeps the existing title, company, export time, total records, filters, frozen headers, widths, wrapping, and alternating row styles.
- Adds regression checks that open every generated XLSX package and confirm that worksheet rows are present and ordered correctly.

## Deployment

No migration, Composer package, or frontend build is required.

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
