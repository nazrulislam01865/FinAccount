# Add Log Filter Two Row Layout

Updated the Log List / Add Log filter area to prevent cramped single-row layout after adding Driver and Vehicle filters.

## Updated files

- `resources/views/fleetman/driver-attendance.blade.php`
- `public/css/fleetman.css`

## What changed

- Date from and Date to fields now have dedicated layout classes.
- Apply/Clear action group now has a dedicated layout class.
- Desktop layout now uses two clean rows:
  - Row 1: Search, Status, Contract, Vehicle, Driver
  - Row 2: Date from, Date to, Apply, Clear
- Tablet layout adapts to 3 columns.
- Mobile layout stacks cleanly in one column with Apply/Clear side by side.

## Deployment

Run after replacing files:

```bash
php artisan optimize:clear
```
