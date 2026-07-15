# Transaction Head Rule Column + Delete Fix

Changed:
- Added an Accounting Rule column on the Transaction Heads list.
- The column shows whether each head has linked accounting rules and displays active/total counts.
- Single and bulk delete now also works when the safe-delete modal JavaScript is stale or not loaded.
- Deleting a transaction head now removes head-specific accounting rules, clears transaction/feed-setting references, and marks affected transactions/journals incomplete.
- Feed auto setup no longer silently recreates a Feed Purchase/Sale transaction head after it was intentionally deleted and the feed setting was cleared.

Run after replacing files:

```bash
php artisan optimize:clear
php artisan view:clear
```

No migration is required.
