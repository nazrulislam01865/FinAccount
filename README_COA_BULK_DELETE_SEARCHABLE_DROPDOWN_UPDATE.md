# COA Bulk Delete and Searchable Dropdown Update

## What changed

### Chart of Accounts bulk delete
- Added selectable checkboxes in the Chart of Accounts list.
- Added a `Delete Selected` bulk action.
- Bulk delete uses the existing safe-delete confirmation modal.
- Parent COA records can be deleted in bulk only when their child accounts are also selected.
- Records are deleted from the deepest level first, so Level 3 ledgers are deleted before Level 2 and Level 1 parents.
- Existing dependency behavior is preserved: mapped money accounts, parties, transaction heads, journal lines, and opening balances are handled the same way as single safe delete.

### Searchable dropdowns
- Existing HisebGhor searchable select component is now applied automatically to accounting dropdown fields.
- COA Parent Account, Type, Normal Balance, report filters, transaction dropdowns, party/money account dropdowns, and configuration dropdowns are searchable.
- Existing `data-hg-searchable` support remains available.
- Add `data-hg-searchable-ignore` to any select that should stay as a native browser dropdown.

## New route

```php
DELETE /chart-of-accounts/bulk-delete
name: chart-of-accounts.bulk-destroy
```

## Changed files

- `routes/web.php`
- `app/Http/Controllers/Accounting/ChartOfAccountController.php`
- `resources/views/chart-of-accounts/index.blade.php`
- `resources/js/pages/chart-of-accounts.js`
- `resources/js/pages/searchable-select.js`
- `resources/css/pages/hisebghor.css`
- `public/build/manifest.json`
- `public/build/assets/app-*.js`
- `public/build/assets/app-*.css`

## Deployment commands

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
