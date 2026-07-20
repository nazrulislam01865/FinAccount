# Transaction Head Hidden Code Field Update

## What changed

- The Transaction Head **Code** field is no longer visible in the Add/Edit modal.
- A hidden code value is retained so existing draft/edit modal JavaScript remains compatible.
- The backend remains authoritative and generates a unique code from the Head Name when a record is saved.
- The generated code continues to appear in the Transaction Heads table.
- A short note below Head Name explains where the generated code will appear.

## Code generation behavior

`TransactionHeadService` calls `AutomaticCodeService::transactionHeadCode()` inside a database transaction.

Examples:

- `Product Sales` becomes `PRODUCT-SALES`
- A duplicate name receives a suffix such as `PRODUCT-SALES-2`
- When a head name is changed, its code is regenerated uniquely

The browser-provided hidden value is not trusted as the final code; the service overwrites it during create/update.

## Allowed Payment Types

These checkboxes control which payment outcomes are permitted when a transaction uses the head:

- `CASH` — Fully paid/received
- `PARTIAL` — Partially paid/received
- `CREDIT` — Fully due

The user does not manually choose the internal payment type on the transaction form. The system infers it by comparing **Paid/Received Now** with the total amount:

- paid now = total amount -> Fully paid/received
- paid now = 0 -> Fully due
- paid now is greater than 0 but less than total -> Partially paid/received

The selected transaction head is checked in both preview and final posting. If the inferred outcome is not enabled for that head, posting is rejected.

## Deployment

No migration or frontend rebuild is required. Run:

```bash
php artisan optimize:clear
```
