# Party Setup: Sub Type and Ledger Nature

## Final behavior

- **Party Sub Type / Category** is optional business classification only.
  - Examples: Retail Customer, Feed Supplier, Contract Employee.
  - It is normalized for extra spaces.
  - It never selects a ledger, Debit/Credit side, journal rule, report group, or opening-balance side.

- **Primary Accounting Nature** is automatic.
  - Users cannot edit it from Party Setup.
  - Party Type provides the default nature.
  - Active party ledger mappings remain the accounting source of truth.
  - Supported natures: Receivable, Payable, Advance Paid, Advance Received, Capital, and No Effect.

- **Explicit mappings** control posting.
  - Credit/customer activity uses the Receivable mapping.
  - Supplier/employee/lender activity uses Payable or the specialized payable mapping.
  - Owner/partner activity uses Capital.
  - Opening balances use the mapping attached to the selected COA account.

## Compatibility

The legacy `parties.default_ledger_nature` and `linked_ledger_account_id` columns are retained for old reports and existing records. They are populated automatically and are not the primary transaction-posting configuration.

## Deployment

```bash
php artisan migrate
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The normalization migration is non-destructive. It cleans blank Sub Type values, adds Capital handling for owner/partner types, and backfills missing purpose-specific party mappings from legacy linked ledgers.
