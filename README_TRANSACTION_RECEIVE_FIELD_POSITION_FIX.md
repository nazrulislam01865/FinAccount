# Transaction Receive/Pay Account Field Position Fix

## Updated

- Moved **Receive In / Pay From** below **Amount** and **Received/Paid Now** in the transaction entry form.
- The money-account field now appears immediately after selecting a Transaction Head that supports full or partial payment.
- The field no longer waits for the user to enter the Amount before becoming visible.
- After the amounts are entered, the existing automatic settlement logic still controls the field:
  - Full/partial payment: Receive In / Pay From remains visible and required.
  - Full due: Receive In / Pay From is hidden and is not required.
- No database migration or backend accounting change was added.

## Local commands

```bash
php artisan optimize:clear
npm install
npm run build
php artisan serve
```
