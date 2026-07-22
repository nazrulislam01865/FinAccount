# Report Total Octane KPI Card Update

Updated all FleetMan report pages so the KPI card section includes Total Octane, matching the existing Total Diesel card style.

## Changed
- Daily Driver & Fuel Report: added `Total Octane (L)` KPI card.
- Monthly Driver Fuel Summary Report: added `Total Octane (L)` KPI card.
- Weekly Driver Fuel Summary Report already had the card and remains supported.
- `public/js/fleetman-reports.js` now updates `#kpiOctane` for daily and monthly reports.
- Report KPI grid now uses responsive auto-fit columns so 7 cards align cleanly on desktop/tablet/mobile.

## Files changed
- `resources/views/fleetman/reports/daily-driver-fuel.blade.php`
- `resources/views/fleetman/reports/monthly-driver-fuel.blade.php`
- `public/js/fleetman-reports.js`
- `public/css/fleetman.css`
