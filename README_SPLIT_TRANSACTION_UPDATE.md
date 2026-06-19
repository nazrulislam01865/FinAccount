# Split / Partial Transaction Update

This update adds optional split transaction support without changing the existing normal transaction flow.

## Accounting behavior

Normal transactions keep the existing rule-based 2-line journal posting.

Partial transactions create one balanced journal entry with 3 journal lines:

### Partial sale

```text
Dr Selected Money Account       paid amount
Dr Party Receivable             due amount
    Cr Head Posting Account     total invoice/sale amount
```

### Partial purchase / expense / asset buying

```text
Dr Head Posting Account         total purchase/expense amount
    Cr Selected Money Account   paid amount
    Cr Party Payable            due amount
```

The remaining due is not stored in a separate ledger. It is posted through `journal_lines.party_id`, so the existing ledger, trial balance, due report, due management, and financial statements continue to work from the accounting journal.

## Files changed

- `database/migrations/2026_06_19_000000_add_split_transaction_fields.php`
- `app/Models/Transaction.php`
- `app/Services/Accounting/TransactionSettlementService.php`
- `app/Services/Accounting/JournalBuilder.php`
- `app/Services/Accounting/TransactionPostingService.php`
- `app/Services/Accounting/TransactionUpdateService.php`
- `app/Http/Requests/Accounting/StoreTransactionRequest.php`
- `app/Http/Requests/Accounting/UpdateTransactionRequest.php`
- `app/Http/Controllers/Accounting/TransactionEntryController.php`
- `resources/views/transactions/create.blade.php`
- `resources/views/transactions/partials/preview.blade.php`
- `resources/views/transactions/index.blade.php`
- `resources/js/pages/transaction-entry.js`
- `app/Http/Controllers/Accounting/TransactionRegisterController.php`

## Deployment command

Run migrations and rebuild assets after pulling the updated code:

```bash
php artisan migrate --force
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
