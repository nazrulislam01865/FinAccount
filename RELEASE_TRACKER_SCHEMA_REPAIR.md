# Release Tracker Schema Repair

## Problem fixed

An older `fleet_releases` table could already exist before the current release tracker migration ran. The original create migration intentionally returned when the table existed, so required columns such as `version`, `title`, `release_date`, and audit fields were never added. The form then failed during the unique-version validation with:

```text
SQLSTATE[42S22]: Unknown column 'version' in 'where clause'
```

## Implementation

Migration added:

```text
database/migrations/2026_06_23_120000_repair_fleet_releases_schema.php
```

It safely:

- leaves the table and existing release history in place;
- adds every missing column required by the current model, controller, list, and form;
- maps compatible values from common legacy column names where available;
- fills safe values only where old rows are missing required data;
- resolves duplicate legacy version labels without deleting records;
- adds a unique version index when the legacy database permits it;
- avoids unsafe foreign-key creation over unknown legacy user references.

## Apply the fix

```bash
php artisan optimize:clear
php artisan migrate
php artisan optimize:clear
```

To inspect the migration first:

```bash
php artisan migrate:status
```

No table should be dropped, and existing release entries are preserved.
