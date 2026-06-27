# Mobile Searchable Dropdown Selection Fix

This update fixes the mobile searchable dropdown where the bottom-sheet appeared dimmed and options could not be selected.

## Fixed

- Searchable dropdown panel is portaled to `<body>` on mobile so it is always above the backdrop.
- Backdrop no longer blocks taps/clicks inside the dropdown panel.
- Mobile option list remains scrollable and touch-friendly.
- Existing desktop dropdown behavior remains unchanged.
- COA normal balance inherited-from-parent behavior is preserved.

## Main changed files

- `resources/js/pages/searchable-select.js`
- `resources/css/pages/hisebghor.css`
- `public/build/manifest.json`
- `public/build/assets/app-*.js`
- `public/build/assets/app-*.css`

## After upload

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
