# Logout Feature Update

The project now exposes secure logout controls for both authenticated areas.

## System Admin
- Added a visible account panel and Logout button to the accounting sidebar.
- Uses Laravel Fortify's existing POST `/logout` route.
- Includes CSRF protection and Laravel's normal session invalidation flow.

## Landing Admin
- Added a dedicated account panel and Logout button to the landing-admin sidebar.
- Uses the separate `landing_admin` guard and POST `/landing-admin/logout` route.
- Clears the landing-admin activity timestamp, rotates the session ID, regenerates the CSRF token, and returns a success message on the Landing Admin login page.

No prior dashboard, menu, transaction, reporting, or configuration functionality was removed.
