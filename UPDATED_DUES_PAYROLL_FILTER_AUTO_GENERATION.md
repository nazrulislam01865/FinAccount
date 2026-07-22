# Dues & Payroll Filter + Automatic Monthly Generation Update

## What changed

1. Dues & Payroll list now filters from the backend instead of filtering only the currently loaded rows.
2. Search now checks the full dues table, including code, type, party, source, status, and payload text.
3. Date filter now uses `due_date` first. If a row has no `due_date`, it falls back to `created_at`.
4. Type and Status filters are dynamic, so any existing/custom status can be selected.
5. KPI cards now show totals from the full filtered dataset, not only the current page.
6. Dues list uses proper page-wise numbered pagination.
7. Manual payroll generation uses the same duplicate-safe service as the automatic scheduler.
8. Automatic monthly payroll/rent due generation is scheduled for the 26th of every month at 00:20 Asia/Dhaka.
9. Source dues now follow all source statuses:
   - Fuel Recharge due is created for any status when the payable amount is greater than zero.
   - Trip balance due is created for any status when balance due is greater than zero.
   - Driver hourly log due is not blocked by log status; it is created when payable hours and amount exist.

## Automatic generation requirement

Laravel's scheduler must be running on the server. Add this cron entry on the droplet if it is not already present:

```bash
* * * * * cd /var/www/FleetManagement && php artisan schedule:run >> /dev/null 2>&1
```

## Manual command

To generate current-month dues manually:

```bash
php artisan fleet:generate-monthly-payroll
```

To generate a specific month:

```bash
php artisan fleet:generate-monthly-payroll --month=2026-07
```

The generation is duplicate-safe. Re-running the same month preserves existing rows and does not create duplicates.
