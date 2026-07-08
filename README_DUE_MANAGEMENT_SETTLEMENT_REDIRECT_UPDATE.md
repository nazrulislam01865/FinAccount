# Due Management Settlement Redirect Update

## What changed

Due Management no longer shows inline settlement fields inside the outstanding dues table.

Instead, each outstanding due now shows one action button:

- `Settlement / Receive` for receivable dues
- `Settlement / Pay` for payable dues

Clicking the button redirects to Transaction Entry with the due context prefilled.

## New user flow

1. Open **Reports → Due Management**.
2. Find the outstanding customer/supplier due.
3. Click the settlement button.
4. Transaction Entry opens with these details already selected:
   - transaction type
   - transaction head
   - party
   - due ledger
   - total outstanding due
5. User enters the settlement amount and selects the cash/bank/mobile account.
6. User posts the transaction.

## Accounting logic

The actual posting still uses the existing Transaction Entry and accounting rule system.

Receivable settlement:

```text
Dr Cash/Bank/Mobile
    Cr Customer Receivable
```

Payable settlement:

```text
Dr Supplier Payable
    Cr Cash/Bank/Mobile
```

## Safety validation

When posting from the due settlement flow, the system validates that:

- the party matches the due row
- the due ledger matches the party mapping
- the transaction head is linked to the selected due ledger
- the settlement amount is not greater than the current due balance

## Files changed

- `resources/views/reports/due-management.blade.php`
- `resources/views/transactions/create.blade.php`
- `resources/js/pages/transaction-entry.js`
- `resources/css/pages/hisebghor.css`
- `app/Http/Controllers/Accounting/TransactionEntryController.php`
- `app/Http/Requests/Accounting/StoreTransactionRequest.php`
