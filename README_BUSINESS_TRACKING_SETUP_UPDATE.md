# Business Tracking Setup Update

Implemented the dynamic Business Tracking Setup screen from the prototype.

## Added
- New `feed_business_tracking_units` table for Cattle, Fish, and Vegetables tracking units.
- New `feed_business_tracking_settings` table for tracking rules.
- New `feed_business_tracking_default_assignments` table for inventory/warehouse defaults.
- New Business Tracking page under Feed Business.
- Dynamic Add/Edit Tracking Unit form with business-specific labels:
  - Cattle → Shed
  - Fish → Pond
  - Vegetables → Vegetable / Crop
- Dynamic Tracking Rules toggles.
- Dynamic Default Assignments table and entry form.
- Sidebar and feed module tab entry for Business Tracking.

## Deployment
Run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then rebuild assets if deploying from source:

```bash
npm install
npm run build
```
