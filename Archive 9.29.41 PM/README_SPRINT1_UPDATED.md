# Sprint 1 Full Code With Auth - Updated

This package includes frontend, backend, auth, default Laravel migrations, and Sprint 1 accounting setup modules.

## Included fixes

- Includes `routes/auth.php`.
- Includes Breeze-style auth controllers and views.
- Includes `resources/js/bootstrap.js` for Vite.
- Includes default Laravel migrations for `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs`.
- Fixes migration order issue where `party_types` referenced `chart_of_accounts` before it existed.
- Keeps Master Setup removed from the UI while preserving seeded dynamic dropdown master data.

## Install

1. Create a fresh Laravel project.
2. Copy this package's `app`, `database`, `resources`, and `routes` contents into the project root.
3. Run:

```bash
composer dump-autoload
php artisan optimize:clear
npm install axios
npm install
npm run build
php artisan migrate:fresh --seed
php artisan serve
```

Login:

- Email: `admin@example.com`
- Password: `password`

## Pages

- `/login`
- `/register`
- `/dashboard`
- `/setup/company`
- `/setup/chart-of-accounts`
- `/setup/cash-bank-accounts`
- `/setup/parties`
- `/setup/transaction-heads`
- `/settings/users-roles`
