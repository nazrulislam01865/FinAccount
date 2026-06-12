# Transaction Head Setup Update

## Final responsibility split

- **Transaction Head**: business activity name, category and Posting COA.
- **Accounting Rule**: settlement, Party requirement, Money Account requirement and other required inputs.
- **Accounting Rule Lines**: Debit/Credit side and ledger source.

## User-facing Transaction Head fields

1. Automatic Transaction Head ID (read-only)
2. Transaction Head Name
3. Category
4. Posting COA
5. User Guidance (optional)
6. Status

## Removed from the Transaction Head form

- Nature
- Default movement
- Party required / Party type
- Payment method or Cash/Bank required
- Settlement Type selection
- Transaction screen
- System default
- User selectable
- Sort order
- Linked Accounting Rule code
- Reference required
- Developer note
- Duplicate description field

`linked_accounting_rule_code`, `developer_note`, and the duplicate `description` column are removed by migration. Other legacy columns remain hidden and system-derived temporarily because historical reports and legacy rule installations still reference them.

## Safety and scalability

- ID is generated server-side using category prefixes, e.g. `TH-EXP-0001`.
- ID and company ownership are immutable.
- Names and IDs are scoped by company.
- Category and Posting COA cannot change after transaction history exists.
- A Head is available in Transaction Entry only when it has a valid active Accounting Rule.
- A modern rule is considered ready only when it has Debit and Credit lines and all required fixed/Head ledgers are configured.
- Settlement Types are derived from active Accounting Rules; the old pivot is fallback-only.
- Used, configured, or system Heads cannot be hard-deleted; use Inactive status.

## Deployment

```bash
php artisan migrate
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
