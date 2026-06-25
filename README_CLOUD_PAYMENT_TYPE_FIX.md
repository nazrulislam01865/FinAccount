# Cloud Payment Type Fix

## Problem fixed

On the cloud database, the `settlement_type` master records could be incomplete or inactive. The Accounting Rule Template form loaded payment types from those database rows, so Expense could show only **Paid/received in full** and the select appeared locked.

## Changes

- Rule Template and Transaction Head setup pages now use the canonical system payment types:
  - `CASH` — Paid/received in full
  - `CREDIT` — Fully due
  - `PARTIAL` — Part paid, remaining due
- Validation now accepts the canonical system payment types even when old cloud master rows are missing.
- Added a corrective migration that restores/activates all three payment types, updates transaction-type metadata, and enables all three payment types on existing transaction heads.
- Made the Rule Template JavaScript fall back to all available options if cloud metadata is missing or malformed.
- Rebuilt the production Vite files locally. The server does not need Node or npm for this package.

## Cloud deployment commands

```bash
cd /var/www/hisebghor

php artisan down

git fetch origin main
git pull --ff-only origin main

sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

sudo chown -R www-data:www-data /var/www/hisebghor
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 755 public/build

sudo systemctl restart php8.4-fpm
sudo nginx -t && sudo systemctl reload nginx

php artisan up
```

After deployment, hard-refresh the browser once (`Cmd+Shift+R` on macOS or `Ctrl+F5` on Windows).
