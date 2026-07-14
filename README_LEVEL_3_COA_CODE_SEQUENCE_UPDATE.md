# Level 3 COA Code Sequence Update

## New numbering rule

- Level 1 remains: `1000`, `2000`, `3000`, ...
- Level 2 remains: `1100`, `1200`, `1300`, ...
- Level 3 now increases by `1` under its Level 2 parent:
  - Parent `1100` → `1101`, `1102`, `1103`, ... `1199`
  - Parent `1200` → `1201`, `1202`, `1203`, ... `1299`

## What was changed

1. `AutomaticCodeService` now uses `LEVEL_THREE_STEP = 1`.
2. Level 3 child capacity is now 99 accounts per Level 2 parent instead of 9.
3. Existing assigned Level 3 COA rows are resequenced automatically by migration.
4. Existing relationship IDs are not changed, so journal lines, money accounts, parties, transaction heads, opening balances, and feed settings continue pointing to the same COA records.

## Existing data migration

Migration file:

`database/migrations/2026_07_13_000400_resequence_level_three_chart_of_account_codes.php`

The migration:

1. Finds assigned Level 3 accounts whose parent is a Level 2 account.
2. Groups them by company and Level 2 parent.
3. Keeps their existing order by old numeric code and record ID.
4. Changes only the `code` field to the new `+1` sequence.
5. Uses temporary safe codes during the update so the existing unique `company_id + code` constraint does not block the resequence.
6. Leaves unassigned legacy Level 3 accounts unchanged because they do not have a Level 2 parent from which a safe hierarchy sequence can be calculated.
7. Runs inside a database transaction and stops without partial changes if it detects malformed hierarchy data or a code collision.

## Example

Before migration under Level 2 parent `1100`:

- `1110` Cash in Hand
- `1120` Bank Account
- `1130` Mobile Banking

After migration:

- `1101` Cash in Hand
- `1102` Bank Account
- `1103` Mobile Banking

## Deployment

Run:

```bash
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

## Main changed files

- `app/Services/Accounting/AutomaticCodeService.php`
- `database/migrations/2026_07_13_000400_resequence_level_three_chart_of_account_codes.php`
- `tests/Feature/Accounting/ChartOfAccountHierarchyTest.php`
- `README_LEVEL_3_COA_CODE_SEQUENCE_UPDATE.md`
