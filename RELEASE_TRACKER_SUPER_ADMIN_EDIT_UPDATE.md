# Release Tracker Super Admin Edit Update

## Implemented

- Added an **Edit** option to every row of the Release Tracker / Release Notes list.
- Added the same Edit option to the responsive mobile release cards.
- Edit controls are rendered only for an active **Super Admin**.
- Added a dedicated edit route and edit page that preloads the selected release data.
- Reused the existing release validation and update operation.
- The updater is recorded in `updated_by_user_id` after a successful save.
- Admin User, Supervisor, Field Officer, Fuel Operator, and other non-Super-Admin roles remain read-only.
- Direct attempts by non-Super-Admin users to open or submit the edit page return HTTP 403.
- No database migration is required.

## Updated files

- `app/Http/Controllers/Fleet/ReleaseTrackerController.php`
- `routes/web.php`
- `resources/views/fleetman/system/release-tracker-list.blade.php`
- `resources/views/fleetman/system/release-tracker-form.blade.php`
- `tests/Feature/ReleaseTrackerAccessTest.php`

## Local refresh commands

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
