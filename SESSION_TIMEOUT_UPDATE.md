# Admin Session Timeout Update

The admin panel now signs an administrator out after **30 minutes without user interaction**.

## Included behavior

- Browser inactivity timer tracks mouse, keyboard, touch, pointer and scroll activity.
- Active form editing is kept alive with a lightweight authenticated heartbeat.
- The timeout is also checked by Laravel middleware, so disabling JavaScript does not bypass it.
- Remembered/cached authentication is checked against the same inactivity window.
- Expired sessions are logged out and redirected to `/admin/login`.
- The login page shows the timeout reason in a red alert.
- Authenticated administrators opening or posting the login page are redirected to the dashboard with a notice.
- Login responses use `no-store` headers and browser back-forward cache protection.
- The standalone logo above the compact/mobile login form was removed.

## Configuration

The default timeout is configured in `config/admin.php`:

```php
'session_timeout_minutes' => (int) env('ADMIN_SESSION_TIMEOUT', 30),
```

Optionally add this to the production `.env`:

```env
ADMIN_SESSION_TIMEOUT=30
SESSION_LIFETIME=30
```

## Deployment

No database migration is required. After uploading the project, run:

```bash
cd /var/www/bashir-agro
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The updated `public/build` assets are already included in this package.
