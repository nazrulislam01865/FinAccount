# Transaction Entry Horizontal Scroll Final Fix

This update removes the remaining page-level horizontal scrolling on the Transaction Entry page, especially after clicking Transaction Direction or Transaction Type tabs.

## What changed

- Added a final overflow guard for the accounting shell, main content, and transaction entry layout.
- Constrained desktop sidebar + main grid width so the main area cannot force the document wider than the viewport.
- Kept transaction direction/type tabs wrapping inside the filter panel instead of extending the page width.
- Added a small Transaction Entry JavaScript guard to reset horizontal scroll after tab navigation and browser scroll restoration.
- Updated the production Vite assets directly because this package does not include `node_modules`.

## Changed files

- `resources/css/pages/hisebghor.css`
- `resources/js/pages/transaction-entry.js`
- `public/build/assets/app-e_3jkTYT.css`
- `public/build/assets/app-C3yH0MW0.js`

## Deployment

No migration is needed.

Run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
