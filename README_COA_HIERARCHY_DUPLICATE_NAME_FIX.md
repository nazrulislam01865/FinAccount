# COA Hierarchy Dropdown and Duplicate Name Fix

## Fixed issues

1. **Parent Account dropdown order**
   - Previously the dropdown sorted all Level 1 accounts first and then all Level 2 accounts.
   - This made Asset child accounts appear visually under Liability if their codes came after the Liability root.
   - The dropdown now uses the same hierarchical order as the COA list:
     - Level 1 account
     - Its Level 2 children
     - Next Level 1 account
     - Its Level 2 children

2. **Duplicate COA name validation**
   - Added backend validation in `ChartOfAccountService`.
   - The same account name can no longer be used twice in the same company Chart of Accounts.
   - Validation works on both create and update.
   - The check is case-insensitive and ignores the current record while editing.

## Changed file

- `app/Services/Accounting/ChartOfAccountService.php`

## Deployment

Run after upload:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

No new migration is required for this fix.
