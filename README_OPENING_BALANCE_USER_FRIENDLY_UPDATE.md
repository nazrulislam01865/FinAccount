# Opening Balance User-Friendly Form Update

## What changed

The Opening Balance modal was simplified so normal users no longer see all technical fields at once.

### Visible main fields

- Financial Year
- COA Ledger
- Opening Balance
- Balance Side

### Conditional fields

- Party / Sub-ledger now appears only when the selected COA is a party-control ledger.
- Money Account now appears only when the selected COA is a cash/bank ledger.
- Money Account options are filtered to the selected COA ledger.

### Hidden or moved fields

- Status dropdown was removed from the UI.
- Status is automatically posted when the form is submitted.
- Opening Date, Reference, and Note were moved under **More Details**.
- Debit Opening and Credit Opening were replaced by one **Opening Balance** field and a **Balance Side** selector.

## Technical details

Updated files:

- `resources/views/opening-balances/index.blade.php`
- `app/Http/Requests/Accounting/StoreOpeningBalanceRequest.php`

The backend still stores data using the existing `debit` and `credit` columns, so report logic and existing database structure remain compatible.

The request now accepts:

- `opening_amount`
- `balance_side`

Then it automatically maps them to:

- `debit`
- `credit`

## Deployment

Run after uploading the project:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
