# Add Log Driver and Vehicle Filters

Updated the Log List filters on the Add Log / Driver Attendance page.

## Changed

- Added searchable Vehicle filter to the Log List filter bar.
- Added searchable Driver filter to the Log List filter bar.
- Vehicle and Driver filter dropdown suggestions are generated from contract assignments and driver master data.
- Backend `/driver-attendance/records` now accepts `vehicle` and `driver` query filters.
- Filters are applied server-side, so pagination and counters remain correct across all log records.
- Filter layout was adjusted so the new fields fit properly on desktop and remain responsive on small screens.

## Updated files

- `resources/views/fleetman/driver-attendance.blade.php`
- `app/Http/Controllers/Fleet/DriverAttendanceController.php`
- `public/js/fleetman.js`
- `resources/js/fleetman/generated/people.js`
- `public/css/fleetman.css`
