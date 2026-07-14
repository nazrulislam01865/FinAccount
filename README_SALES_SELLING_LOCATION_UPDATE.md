# Sales Transaction: Selling Type and Location/Godown

## Added behavior

The generic **Sales** transaction entry form now includes:

- **What are you selling?**: Fish, Cattle, Vegetable, Others.
- **Location / Godown**: displayed and required only for Fish, Cattle, and Vegetable.
- The location list uses active, company-specific records from **Feed Setup → Warehouses**.
- The default Feed warehouse is remembered and preselected when available.
- Selecting **Others** hides the location field and stores no warehouse.
- The fields are retained when editing a normal Sales transaction.

## Database fields

The `transactions` table now stores:

- `selling_type` (nullable string)
- `warehouse_id` (nullable foreign key to `feed_warehouses`)

Existing transactions remain valid with null values.

## Deployment

Run these commands after replacing the project files:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The production Vite assets are already built in `public/build`.
