# Level 3 COA Code Sequence Update

## New numbering rule

- Level 1 remains: `1000`, `2000`, `3000`, ...
- Level 2 remains: `1100`, `1200`, `1300`, ...
- Level 3 now increases by `1` under its Level 2 parent:
  - Parent `1100` → `1101`, `1102`, `1103`, ... `1199`
  - Parent `1200` → `1201`, `1202`, `1203`, ... `1299`

## Existing data migration

Migration file:

`database/migrations/2026_07_13_000400_resequence_level_three_chart_of_account_codes.php`

The migration:

1. Finds assigned Level 3 accounts whose parent is a Level 2 account.
2. Groups them by company and Level 2 parent.
3. Keeps their existing order by old numeric code and record ID.
4. Changes only the `code` field to the new `+1` sequence.
5. Keeps every Chart of Account ID unchanged, so transaction, journal, money-account, party, transaction-head, and opening-balance relationships remain intact.
6. Leaves unassigned legacy Level 3 accounts unchanged because they do not have a Level 2 parent from which a safe sequence can be calculated.
7. Runs inside a database transaction and stops without partial changes if it detects malformed hierarchy data or a code collision.

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

The compiled frontend assets are already included. If rebuilding from source, also run:

```bash
npm ci
npm run build
```

## Main changed files

- `app/Services/Accounting/AutomaticCodeService.php`
- `database/migrations/2026_07_13_000400_resequence_level_three_chart_of_account_codes.php`
- `resources/js/pages/chart-of-accounts.js`
- `resources/views/chart-of-accounts/index.blade.php`
- `tests/Feature/Accounting/ChartOfAccountHierarchyTest.php`
- `public/build/manifest.json`
- `public/build/assets/app-D8xLF76H.js`
- `public/build/assets/app-DeNqDf2t.css`
