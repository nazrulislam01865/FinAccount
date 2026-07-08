# Opening Balance Clean Design Update

## Updated file
- `resources/views/opening-balances/index.blade.php`

## What changed
- Redesigned the Opening Balance modal into compact card sections.
- Reduced modal width so the form does not look stretched on desktop.
- Fixed Balance Side radio buttons so they display as clean segmented Debit/Credit buttons.
- Forced conditional Party and Money Account fields to stay hidden until the selected ledger requires them.
- Kept the simplified one-amount Opening Balance logic.
- Kept More Details collapsed and styled as a clean optional section.
- Improved mobile responsiveness for the form and action buttons.

## No backend changes
This update only changes the form design. The existing opening balance posting logic is unchanged.

## Deployment
After uploading the updated files, run:

```bash
php artisan optimize:clear
php artisan view:cache
```
