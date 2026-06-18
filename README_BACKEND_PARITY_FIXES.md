# HisebGhor Backend Template-Parity Fixes

Implemented on 17 June 2026.

## 1. Safe dependency-aware deletion

The previously implemented safe-delete workflow remains enabled.

- The delete preview reports all affected dependencies.
- Explicit confirmation is required before deletion.
- Direct foreign-key references are cleared safely inside a database transaction.
- Dependent setup records are made inactive when their required mapping is removed.
- Affected Transactions and Journal Entries are marked `incomplete` so an administrator can repair and repost them.
- Transaction deletion removes its generated Journal Entry and Journal Lines together.
- Nullable `nullOnDelete` foreign keys remain enabled for relationships managed by safe delete.
- Row locks, post-update verification, and deadlock retry remain active.

Sales, Payment, and Liability remain protected core categories and cannot be deleted.

## 2. System Admin authorization

A `role` column was added to `users`.

Available roles:

- `system_admin`
- `accounting_user`

System Admin only:

- Dashboard sample-data reset
- Chart of Accounts
- Money Accounts
- Parties
- Accounting Rules
- Transaction Heads
- Transaction Categories
- Voucher Numbering
- Party Types
- Money Account Types
- Other Master Data

Accounting users can continue to use:

- Dashboard
- Transaction Entry
- Transaction Register
- Journal Entries
- Account and Party Balances
- Financial Statements

All existing users receive `system_admin` during migration so existing installations do not lose access. New database-level user records default to `accounting_user`; the public company-registration flow explicitly creates its company owner as `system_admin`.

## 3. Custom transaction-category vouchers

Every Transaction Category now requires a unique 2–10 character uppercase alphanumeric **Voucher Prefix**.

On the first posted transaction for a category, the backend automatically creates its company-specific Voucher Numbering record and issues the first voucher, for example:

- Category: `Adjustment`
- Prefix: `ADJ`
- First voucher: `ADJ-0001`

Voucher creation is transaction-safe and concurrency-safe through the unique company/category sequence and row locking.

## 4. Historical report stability

Cash classifications now use the Journal Lines stored when the transaction was posted.

Editing an Accounting Rule later no longer changes:

- Sales Collected
- Payments Made
- Dashboard paid/unpaid sale classification

Account balances and statements continue to come from posted journal history.

## 5. Automated-test alignment

Regression coverage was added or corrected for:

- System Admin versus Accounting User access
- Dependency preview and confirmed safe deletion
- Repairing transactions after dependency detachment
- Voucher-numbering safe deletion
- Explicit transaction-delete confirmation
- Automatic custom-category voucher creation
- Historical reports remaining stable after rule edits

## Deployment

After replacing the project files, run:

```bash
php artisan down || true
php artisan migrate --force
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Before production migration, take a database backup. The final migration restores the nullable foreign-key behavior required by the safe-delete workflow, including on servers where the temporary restrictive migration was already applied.
