# Transaction Entry Auto Payment Update

The visible **How was payment handled?** field has been removed from Transaction Entry.

Payment handling is now determined in the backend from **Amount** and **Received Now / Paid Now**:

- Received/Paid Now equals Amount: `CASH`
- Received/Paid Now is `0`: `CREDIT`
- Received/Paid Now is greater than `0` but less than Amount: `PARTIAL`

The backend recalculates this value and does not trust the hidden browser value.

## User-interface changes

- `What was it?` renamed to `Transaction Head`
- `Total Amount` renamed to `Amount`
- `Received In` renamed to `Receive In`
- `Paid From` renamed to `Pay From`
- Transaction-type tabs now use short labels
- The large selected-activity message was removed
- The form heading now follows the previous style, such as `Record Sales Transaction`
- Amount paid/received now defaults to the full amount for faster cash transactions
- Party, money account, due amount, and due date fields appear automatically when required

## Local commands

```bash
php artisan optimize:clear
npm install
npm run build
php artisan serve
```

No new database migration is required for this form update.
