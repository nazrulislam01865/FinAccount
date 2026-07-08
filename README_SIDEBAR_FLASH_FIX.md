# Sidebar Flash Fix

This update stabilizes the accounting sidebar when users click menu or submenu links.

## Changed files

- `resources/views/layouts/accounting.blade.php`
- `resources/js/pages/accounting-navigation.js`
- `resources/css/pages/accounting-navigation.css`
- `public/build/manifest.json`
- `public/build/assets/app-CRKmvWax.css`
- `public/build/assets/app-BeFM7XCQ.js`

## What changed

- Added a temporary `hg-sidebar-booting` body class during first paint.
- Disabled sidebar/submenu transitions while the page is loading or navigating.
- Added `hg-sidebar-navigating` before page changes to prevent slide/flicker effects.
- Kept the active submenu open and stopped session-stored submenu state from closing the current active group.
- Preserved sidebar scroll position and submenu state without visible jump.
- Rebuilt Vite assets locally so deployment can use the included frontend build.

## Deployment

Run after uploading/pulling the update:

```bash
php artisan optimize:clear
php artisan view:cache
```

If you deploy frontend assets from local, upload the new `public/build` folder as usual.
