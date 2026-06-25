# Transaction Party Auto-Selection Update

## Purpose

The transaction entry page no longer asks the user to choose **Payable To / Customer / Supplier / Owner / Lender** when the selected Transaction Head and company setup identify exactly one valid active party.

## Behaviour

- Full cash Sale, Purchase, Expense, and Asset Purchase: party field remains hidden because no receivable/payable is created.
- Due or partial transaction:
  - exactly one active party matches the Transaction Head party type: selected automatically; dropdown hidden;
  - more than one active matching party exists: dropdown remains visible and required;
  - no active matching party exists: transaction is blocked with a setup message.
- Party selection is verified again in the backend. JavaScript cannot force an invalid party type.
- No database migration or additional field was added.

## Why the field cannot always be hidden

A Transaction Head stores a party type such as Worker or Supplier, not one fixed party. If multiple workers or suppliers exist, automatically choosing the first one would post the due to the wrong party ledger. The user therefore chooses only when the choice is genuinely necessary.

## Example

Salary Expense head:

- Required party type: Worker
- Active Workers: one
- Result: Worker selected automatically and **Payable To** hidden

If two active Workers exist, **Payable To** appears so the correct worker can be chosen.

## Updated files

- `app/Services/Accounting/TransactionPartyResolver.php`
- `app/Services/Accounting/TransactionPostingService.php`
- `app/Services/Accounting/TransactionUpdateService.php`
- `app/Http/Controllers/Accounting/TransactionEntryController.php`
- `resources/views/transactions/create.blade.php`
- `resources/js/pages/transaction-entry.js`
- transaction feature tests

## Deployment

```bash
mkdir -p storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    bootstrap/cache

chmod -R 775 storage bootstrap/cache

php artisan optimize:clear
npm install
npm run build
php artisan serve
```

No migration is required.
