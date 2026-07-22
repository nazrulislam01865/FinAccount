# Fleet Management performance deployment

This update intentionally keeps the existing cache and session drivers. Redis was **not** introduced.

## Application deployment

From the project directory, run:

```bash
APP_DIR=/var/www/FleetManagement bash deploy/optimize-production.sh
```

The script installs production dependencies, builds both the existing Vite assets and the new split FleetMan assets, runs the indexed-column migration, synchronizes the role matrix once, and rebuilds Laravel's production caches.

If the server cannot access the configured remote font provider during the Vite build, use:

```bash
FLEET_OFFLINE_BUILD=1 APP_DIR=/var/www/FleetManagement bash deploy/optimize-production.sh
```

## PHP OPcache

```bash
sudo cp deploy/99-fleet-opcache.ini /etc/php/8.4/fpm/conf.d/99-fleet-opcache.ini
sudo systemctl restart php8.4-fpm
```

Confirm it is active:

```bash
sudo php-fpm8.4 -i | grep -E 'opcache.enable =>|opcache.memory_consumption|opcache.max_accelerated_files'
```

## Nginx compression and asset caching

Copy the directives from `deploy/nginx-fleet-performance.conf` inside the active Fleet Management `server { ... }` block. Do not create a second conflicting `location /` block.

Then validate and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Important deployment rule

`FleetRbac::syncDefaults()` no longer runs during normal page requests. Run this once after deploying new permissions:

```bash
sudo -u www-data php artisan fleet:rbac-sync
```

The deployment script already runs this command.
