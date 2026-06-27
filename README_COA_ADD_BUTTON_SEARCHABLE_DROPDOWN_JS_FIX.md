# COA Add Button + Searchable Dropdown JS Fix

## Problem
After the mobile searchable dropdown update, the **+ Add COA** button stopped opening the modal.

## Root cause
`resources/js/pages/searchable-select.js` called `syncMobileState()` before `panelHome` was initialized. When the page loaded and the searchable select enhancement ran, JavaScript threw an error and stopped the remaining page scripts from binding events, including the `data-coa-open="create"` Add COA button handler.

## Fix
- Initialize `panelHome` before any panel placement logic runs.
- Move the initial `syncMobileState()` call until after the dropdown panel has been appended and element references are ready.
- Rebuilt Vite assets so the production `public/build` JS uses the fixed code.

## Preserved
- Mobile searchable dropdown select fix.
- COA normal balance inheritance from parent.
- COA bulk delete.
- COA hierarchy ordering and duplicate-name validation.
- Opening balance separated module.

## After deploy
Run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then hard refresh the browser.
