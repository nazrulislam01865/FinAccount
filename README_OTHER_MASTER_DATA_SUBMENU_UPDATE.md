# Other Master Data Sidebar Submenu Update

## Summary
The sidebar navigation was cleaned up so master-data modules listed on the Other Master Data page are no longer shown as separate direct sidebar menu items.

## Changed
- Removed direct top-level sidebar links for:
  - Party Types
  - Money Account Types
  - Transaction Types
  - Voucher Numbering
- Added them under the **Other Master Data** sidebar submenu.
- Added Company Setup master modules under the same submenu:
  - Business Types
  - Currencies
  - Time Zones
  - Financial Years
- Kept existing permission checks, add-only routing behavior, and active menu highlighting.
- The submenu automatically opens when the user is inside any Other Master Data route.

## File changed
- `resources/views/partials/accounting/sidebar.blade.php`

## Deployment notes
No migration or build is required for this change.

Run after deployment:

```bash
php artisan optimize:clear
php artisan view:cache
```
