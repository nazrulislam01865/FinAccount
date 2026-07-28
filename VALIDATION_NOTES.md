# Validation notes

Validated in the generated source package:

- PHP syntax passed for all application, configuration, migration, route and test PHP files.
- `public/flowtrack-dynamic.js` passed Node syntax validation.
- Admin/Super Admin role codes are forced to all modules, all actions, `all_records`, and every sensitive field.
- Super Admin seed is idempotent and reads credentials from `.env`.
- User creation/deletion endpoints are restricted to Admin/Super Admin.
- Admin cannot alter a Super Admin account or assign the Super Admin role.
- Role matrix checks are applied to clients, Jobs, tasks, documents, reports, global search, workflow, master data and administration.
- Legacy Laravel permission slugs are synchronized from the matrix for older Inertia routes.
- Runtime storage directories and configuration are included.

The container did not include Composer dependencies, so Laravel route boot, migrations, database integration tests and the full test suite could not be executed here. Run these after extraction:

```bash
composer install
php artisan migrate
php artisan db:seed --class="Database\\Seeders\\FlowTrack\\SuperAdminSeeder"
php artisan route:list --path=flowtrack
php artisan test
```
