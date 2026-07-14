# Transaction Entry Step Selection Update

This update changes the Transaction Entry upper section into a step-by-step flow.

## New behavior

1. Opening `/transactions/create` shows only the four Transaction Direction buttons:
   - Money In
   - Money Out
   - Transfer
   - Non-Cash

2. After the user clicks a direction, the page shows only the Transaction Types configured under that direction.

3. The transaction form opens only after the user clicks a Transaction Type.

4. Direct links that already include `category=...` still open the selected transaction form, so dashboard shortcuts and draft-continue links continue to work.

## Updated files

- `app/Http/Controllers/Accounting/TransactionEntryController.php`
- `resources/views/transactions/create.blade.php`
- `tests/Feature/TransactionEntryDropdownTest.php`

## Notes

The filtering still uses the Transaction Type setup metadata/flow handled by `App\Support\TransactionTypes` and `AccountingOption` records. No database migration is required for this UI change.
