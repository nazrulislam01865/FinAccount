# COA Mobile Searchable Dropdown + Inherited Normal Balance Update

## What changed

### 1. Mobile-friendly searchable dropdown
- Searchable dropdown now opens as a mobile bottom-sheet/full-screen selector on small devices.
- The search input no longer auto-focuses on mobile unless the user starts typing from keyboard, so the phone keyboard does not cover the option list immediately.
- Touch scrolling is improved inside the option list.
- Long option names can wrap instead of being cut off.
- Larger tap targets were added for mobile usability.

### 2. Chart of Accounts normal balance inheritance
- Level 1 COA keeps normal balance selectable.
- Level 2 and Level 3 COA automatically inherit normal balance from the selected parent.
- The child normal balance selector becomes disabled/read-only and a hidden field submits the inherited value.
- Backend service enforces inheritance, so tampering with the form cannot create a different normal balance for a child account.
- If a parent account normal balance is changed, all descendant accounts inherit the updated normal balance.

## Main files changed
- `resources/js/pages/searchable-select.js`
- `resources/css/pages/hisebghor.css`
- `resources/views/chart-of-accounts/index.blade.php`
- `resources/js/pages/chart-of-accounts.js`
- `app/Services/Accounting/ChartOfAccountService.php`
- `app/Http/Requests/Accounting/StoreChartOfAccountRequest.php`
- `app/Http/Requests/Accounting/UpdateChartOfAccountRequest.php`
- `public/build/manifest.json`
- `public/build/assets/app-*.js`
- `public/build/assets/app-*.css`

## Deployment
Run after upload:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If Laravel says `Please provide a valid cache path`, create the framework folders:

```bash
mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/framework/testing bootstrap/cache
chmod -R 775 storage bootstrap/cache
```
