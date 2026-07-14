# Transaction Entry Direction Filter Update

## What changed

The Transaction Entry page upper selector now works in two steps:

1. **Transaction Direction** buttons show only the four direction options:
   - Money In
   - Money Out
   - Transfer
   - Non-Cash

2. **Transaction Type** buttons are filtered automatically based on the selected direction.
   - Money In shows only transaction types whose Transaction Direction is Money In.
   - Money Out shows only transaction types whose Transaction Direction is Money Out.
   - Transfer shows only transaction types whose Transaction Direction is Transfer.
   - Non-Cash shows only transaction types whose Transaction Direction is Non-Cash.

The filter uses the existing `metadata.flow` value of each Transaction Type from Master Data. Built-in system transaction types still use their existing backend mapping from `App\Support\TransactionTypes::flow()`.

## Files changed

- `app/Http/Controllers/Accounting/TransactionEntryController.php`
- `app/Http/Controllers/Accounting/TransactionRegisterController.php`
- `resources/views/transactions/create.blade.php`
- `resources/css/pages/hisebghor.css`
- `public/build/assets/app-DIRFILTER20260714.css`
- `public/build/manifest.json`
- `tests/Feature/TransactionEntryDropdownTest.php`

## Notes

If a direction has no active Transaction Type setup, the page now shows a clear message instead of loading an unrelated transaction type.

## Run after replacing the project

```bash
php artisan optimize:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

No database migration is required for this change.
