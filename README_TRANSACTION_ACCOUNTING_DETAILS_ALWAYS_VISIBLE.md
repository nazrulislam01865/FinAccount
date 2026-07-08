# Transaction Accounting Details Always Visible Update

Updated the transaction entry preview so the Accounting Details section is always visible whenever a valid transaction preview/journal is available.

## Changed file

- `resources/views/transactions/partials/preview.blade.php`

## What changed

- Removed the collapsed `<details>` / `<summary>` toggle around Accounting Details.
- The journal table now appears immediately under the transaction summary.
- Added debit and credit totals at the bottom of the table.
- Added a Balanced / Not balanced status badge.
- Empty and error states remain unchanged.
- No database or backend posting logic was changed.

## Deployment

Run:

```bash
php artisan optimize:clear
php artisan view:cache
```
