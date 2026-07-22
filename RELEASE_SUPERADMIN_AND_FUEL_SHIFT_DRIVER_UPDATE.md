# Release Super Admin and Fuel Shift Driver Update

## Release Tracker
- Add Release form, store, update, and delete routes are protected by Super Admin middleware.
- Add Release submenu and list-page button are visible only to Super Admin.
- Release List remains available to authenticated users as read-only.

## Add Fuel
- Added a read-only Assigned Driver (Current Shift) field after Contract and Vehicle.
- Single-shift vehicle: shows the assigned contract driver.
- Double-shift vehicle: compares current Asia/Dhaka system time with each assigned shift start/end time and shows the matching driver.
- Overnight shifts are supported.
- The resolved driver and shift details are stored with the fuel entry.
- Shift timing is stored separately from attendance start/end fields.

## Deployment
No migration is required.

Run:

```bash
php artisan optimize:clear
```
