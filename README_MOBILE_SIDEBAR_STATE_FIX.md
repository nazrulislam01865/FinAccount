# HisebGhor Mobile Sidebar and Navigation State Fix

## Completed

- Added a hamburger navigation button to the accounting top bar for screens up to 900px.
- Converted the accounting sidebar into an off-canvas mobile/tablet drawer.
- Added a dimmed backdrop, visible close button, Escape-key support, and focus trapping.
- Prevented the page behind the mobile drawer from scrolling.
- Restored complete menu labels and section headings inside the mobile drawer.
- Kept the existing compact icon sidebar behavior for medium desktop widths.
- Saved and restored the sidebar scroll position for each logged-in user during page navigation.
- Added generic persistence for expandable sidebar groups so future submenu state is retained.
- Automatically keeps the active navigation link visible when there is no saved position.
- Added reduced-motion support.

## Main files

- `resources/views/layouts/accounting.blade.php`
- `resources/views/partials/accounting/sidebar.blade.php`
- `resources/js/pages/accounting-navigation.js`
- `resources/css/pages/accounting-navigation.css`
- `resources/js/app.js`
- `resources/css/app.css`

## Build after deployment

```bash
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
