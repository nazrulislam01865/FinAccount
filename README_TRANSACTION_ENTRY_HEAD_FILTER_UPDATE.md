# Transaction Entry Head Filter Update

## Request
- When no Transaction Direction is selected, the Transaction Head dropdown should show all active transaction heads.
- When a Transaction Direction is selected, the dropdown should filter by that direction.
- When a Transaction Type is also selected, the dropdown should filter by that transaction type.

## Implemented
- Added `TransactionEntryOptionService::allTransactionHeads()` for all active heads with active posting accounts.
- `TransactionEntryController@create` now decides the head list as:
  - no explicit direction/type: all active transaction heads
  - direction only: active heads whose transaction type belongs to that direction
  - direction + transaction type, or transaction type directly: heads for that selected transaction type
- Transaction Head options now carry `data-category` and `data-direction` so the hidden transaction category stays synchronized with the selected head.
- Transaction Entry JavaScript now updates the hidden category from the selected head before journal preview/posting.
- The Sale feed-item section stays available only when the selected/current category is Sale and the selling type is not Others.
- Current production JS asset was updated.

## Deployment
No migration is required.

Run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
