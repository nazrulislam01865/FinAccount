# HisebGhor Production Optimization, Security, Session and Caching Guide

This project now includes safe production defaults for security headers, rate limiting, session timeout handling, cache TTL configuration and additional query indexes. These changes do not alter accounting posting, reports or setup business logic.

## 1. Recommended `.env` values for the droplet

Use `.env.production.example` as the reference and copy the relevant values into the real `.env` file on the server.

Minimum production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_FORCE_HTTPS=true

LOG_CHANNEL=daily
LOG_LEVEL=warning

CACHE_STORE=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_LIFETIME=120
SESSION_INACTIVE_TIMEOUT=15
LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT=15
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

QUEUE_CONNECTION=database

SECURITY_HEADERS_ENABLED=true
SECURITY_CSP_ENABLED=true
SECURITY_HSTS_ENABLED=true
```

If the site is still running on plain HTTP/IP only, keep these two values disabled until SSL is installed:

```env
APP_FORCE_HTTPS=false
SESSION_SECURE_COOKIE=false
SECURITY_HSTS_ENABLED=false
```

After SSL is active, switch them to `true`.

## 2. Cache strategy

Current safe setup for your SME accounting target:

```env
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

This is simple and stable for a small droplet. When traffic grows, install Redis and switch to:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

Use `.env.redis-production.example` as the reference.

## 3. Session strategy

The system login and Landing Admin login remain separate guards. Both use the Laravel session layer, but timeout keys are separated:

- Accounting/system users: `SESSION_INACTIVE_TIMEOUT`
- Landing Admin users: `LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT`

Recommended:

```env
SESSION_ENCRYPT=true
SESSION_INACTIVE_TIMEOUT=15
LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT=15
SESSION_LIFETIME=120
```

## 4. Security protections added

The new `SecurityHeaders` middleware adds:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` for camera, microphone, geolocation and payment blocking
- Content Security Policy compatible with the current Blade/landing UI
- Optional HSTS for HTTPS production

Rate limiting added:

- Landing inquiry/demo form
- Landing Admin login
- General named form limiter for future use

## 5. Database performance indexes added

The migration `2026_05_30_000003_add_performance_security_indexes.php` adds indexes for common production reads:

- user status lookups
- landing admin status lookup
- landing inquiry status/date/email lookups
- audit log company/user/module filters
- chart of accounts parent/status/code filters
- party type/status/name filters
- transaction head status/name filters

Run:

```bash
php artisan migrate --force
```

## 6. Deployment cache commands

After each code update on the droplet:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

If `view:cache` fails because PHP DOM extension is missing, install it for your PHP version, for example:

```bash
apt install php8.4-xml
systemctl restart php8.4-fpm
```

## 7. Queue worker

For reports/export/background jobs, use a queue worker instead of running everything in the web request.

Temporary test:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Production supervisor service is recommended.

## 8. Production check command

Run:

```bash
php artisan app:production-check
```

It checks:

- production environment
- debug disabled
- APP_KEY present
- config cache
- storage permissions
- persistent session/cache drivers
- queue not sync
- session encryption
- security headers

## 9. Nginx-level recommendations

Add/keep these at Nginx level for better performance:

```nginx
gzip on;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
client_max_body_size 20M;
```

For Vite assets in `public/build`, add long cache headers:

```nginx
location /build/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## 10. Safe scaling path

For your current SME target of 4-5 users:

1. MySQL + database cache/session/queue is enough.
2. Add Redis when concurrent usage/report exports grow.
3. Move heavy reports to background jobs.
4. Add reporting DB/read replica only after report queries become heavy.
5. Keep accounting writes on the primary DB.
