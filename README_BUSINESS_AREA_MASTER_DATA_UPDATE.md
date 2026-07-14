# Business Area Master Data Update

## What changed

- Added a dynamic `feed_business_areas` master-data table.
- Added a new Business Area Master Data section on Feed > Business Tracking.
- Added a three-field Add Business Area form:
  - Area Name
  - Unit Label
  - Status
- Business Area dropdowns now load from active master-data records instead of a hard-coded list.
- Existing default areas are auto-created for each company:
  - Cattle
  - Fish
  - Vegetables
- Existing tracking units are backfilled into the new business-area master table.
- Removed the unnecessary vertical stretching/overflow on the Business Tracking setup grid.

## Deployment

Run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The sandbox PHP runtime is missing DOMDocument, so Artisan cache commands cannot complete here, but the changed PHP files passed syntax checks and the production Vite build succeeded.
