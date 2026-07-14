# Transaction Entry Direction Filter Form Restore

## What changed

The Transaction Entry page now only changes the transaction type selector area.

- On first page load, the upper selector shows only the 4 Transaction Direction buttons:
  - Money In
  - Money Out
  - Transfer
  - Non-Cash
- The Transaction Type button row is hidden until a direction is clicked.
- The existing transaction form, transaction summary, draft handling, attachments, payment fields, party fields, and journal preview are not hidden or removed.
- When a direction is clicked, only transaction types configured under that direction are shown.
- If no category is passed, the form continues to use the first active transaction type, like the older page behavior, so the screen is not empty.
- If a direction has no active transaction type, the page shows a setup message instead of an empty form.

## Main files updated

- `app/Http/Controllers/Accounting/TransactionEntryController.php`
- `resources/views/transactions/create.blade.php`
- `tests/Feature/TransactionEntryDropdownTest.php`

## After deployment

Run:

```bash
php artisan optimize:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
