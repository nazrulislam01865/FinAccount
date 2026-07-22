# Petrol and Octane Separation Update

This update separates Petrol and Octane throughout FleetMan while preserving existing fuel recharge amounts, rates, odometer readings, mileage, and TK/KM values.

## Included changes

- Separate Petrol and Octane master-data values.
- Exact Petrol/Octane fuel-price matching.
- Separate recharge payload fields: `petrol`, `octane`, and `petrolOctane` for unresolved legacy entries.
- Separate Petrol and Octane totals in daily, weekly, monthly, CSV, and Excel reports.
- A safe historical conversion command using saved fuel names, vehicle IDs, and optional effective-date mappings.
- Legacy `Petrol/Octane` data remains visible as **Petrol/Octane (Unclassified)** until confidently classified.

## Deployment

Back up the database before applying the historical conversion.

```bash
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan up
```

If frontend assets are built during deployment:

```bash
npm ci
npm run build
```

## Master-data preparation

After migration:

1. Add or verify separate active prices for Petrol and Octane.
2. Change every applicable vehicle from `Petrol/Octane` to its exact fuel: `Petrol` or `Octane`.
3. Update station fuel availability to select Petrol, Octane, or both.

The old combined master value is deactivated rather than deleted so historical references remain safe.

## Historical data conversion

Run a dry run first:

```bash
php artisan fleet:split-petrol-octane
```

After reviewing the summary, apply it:

```bash
php artisan fleet:split-petrol-octane --apply
```

You can supply direct vehicle mappings:

```bash
php artisan fleet:split-petrol-octane \
  --vehicle=VHL001=Petrol \
  --vehicle=VHL002=Octane
```

For a vehicle that changed fuel over time, copy `docs/petrol-octane-vehicle-mapping.example.json`, edit the vehicle IDs and dates, then run:

```bash
php artisan fleet:split-petrol-octane \
  --mapping=storage/app/petrol-octane-vehicle-mapping.json

php artisan fleet:split-petrol-octane \
  --mapping=storage/app/petrol-octane-vehicle-mapping.json \
  --apply
```

## Classification priority

The conversion command classifies each old record in this order:

1. A clear saved fuel name such as Petrol or Octane.
2. An explicit vehicle/date mapping file or `--vehicle` option.
3. The vehicle's current exact Petrol or Octane setup.
4. Otherwise, it keeps the quantity in `petrolOctane` as unresolved instead of guessing.

## Safety guarantees

The command does not recalculate historical prices or amounts. It verifies that:

```text
old combined liquid quantity = new Petrol + Octane + unresolved Petrol/Octane quantity
```

Old ambiguous entries are not silently assigned to either fuel.
