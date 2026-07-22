# Report Filter All Status Fix

Updated the Daily, Weekly, and Monthly Driver/Fuel reports.

## What changed

- Reports now include every status, including Draft, Submitted, Completed, Running, Initiated, null/blank, and any custom status.
- Removed the previous report-side Draft exclusion from:
  - Fuel recharge records
  - Driver attendance/log records
  - Trip cost fallback records
- Fixed report filters so they work across all loaded report records:
  - Contract
  - Vehicle/Car
  - Driver
  - Status
  - Fuel type
  - Daily date range
  - Weekly week selector
  - Monthly month selector
- Searchable filters now support partial text search instead of strict exact value only.
- Contract-dependent Vehicle and Driver filter options now update using partial contract matching.
- Weekly and Monthly reports now also include a Fuel Type filter.
- Daily report label changed from `Draft / Submitted` to `Status`.
- Exports use the same filtered result shown in the report.

## Files changed

- app/Http/Controllers/Fleet/ReportController.php
- public/js/fleetman-reports.js
- public/css/fleetman.css
- resources/views/fleetman/reports/daily-driver-fuel.blade.php
- resources/views/fleetman/reports/weekly-driver-fuel.blade.php
- resources/views/fleetman/reports/monthly-driver-fuel.blade.php
