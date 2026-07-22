# FleetMan All List Table Pagination Update

This full project zip adds page-wise pagination across the main FleetMan list tables.

## Updated behavior

- Vehicle List, Price List, Recharge List, Vendor List, Trip List, Driver List, Client List, Log List, Employee List, Contract List, Yard List now use page-wise numbered pagination.
- Record-backed pages now load 10 records for the first page instead of loading large lists into the page source.
- Search, status, dropdown and date filters trigger server-side pagination resets for record-backed list tables.
- The main total KPI on paginated pages is updated from the full filtered database total, not only the current page rows.
- Previous/Next controls on Contract and Yard list pages are hidden; page number buttons are used.
- Dues list has numbered pagination.
- Simple/static tables such as master-data and system list tables get client-side numbered pagination when they have more than 10 rows.
- The combined `public/js/fleetman.js` is now loaded as the authoritative FleetMan script so this update does not require a frontend rebuild.

## After deploying

Run:

```bash
php artisan optimize:clear
```

If database migrations are pending, also run:

```bash
php artisan migrate --force
```
