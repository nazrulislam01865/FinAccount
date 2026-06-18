# Safe Delete Restoration

The previously implemented safe-delete workflow has been restored without removing the later backend improvements.

## Restored behavior

- Dependency preview remains available before deletion.
- Explicit confirmation is required.
- Used setup records can be safely deleted after confirmation.
- Direct references are cleared before deletion.
- Dependent setup records are deactivated where needed.
- Affected transactions and journal entries are marked `incomplete` so they can be repaired.
- Transaction deletion still removes its generated journal entry and lines together.
- Database relationships use nullable `nullOnDelete` foreign keys required by the safe-delete workflow.

## Preserved later improvements

- System Admin and accounting-user authorization.
- Custom transaction category voucher prefixes and automatic sequence creation.
- Historical reports based on stored journal lines.
- Logout, menu, and dashboard updates.
- Database transactions, row locks, and deadlock retry used by safe-delete operations.

## Deployment

Run:

```bash
php artisan migrate --force
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
