# Session, Rate Limit and Single Active Login Update

Implemented FleetManagement-style session protection for HisebGhor.

## Added

- System/accounting users now have one active browser/device session at a time.
- Landing Admin users also have one active browser/device session at a time.
- New logins replace older active sessions for the same user.
- The older session is logged out on its next request or keep-alive check and receives a clear message.
- 15-minute inactivity auto logout is enforced on the server and by browser timer.
- Keep-alive and timeout routes were added for accounting and Landing Admin sessions.
- Login rate limiting now follows `.env` driven settings in `config/security.php`.
- Logout, timeout, password reset and account deactivation clean active-session markers.

## New migration

```text
2026_06_18_100000_add_active_session_id_to_auth_tables.php
```

It adds `active_session_id` to:

- `users`
- `landing_admin_users`

Run:

```bash
php artisan migrate --force
```

## Important .env settings

```env
SESSION_INACTIVE_TIMEOUT=15
LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT=15
RATE_LIMIT_SYSTEM_LOGIN_ENABLED=true
RATE_LIMIT_SYSTEM_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_SYSTEM_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_SYSTEM_LOGIN_KEY_STRATEGY=email_ip
RATE_LIMIT_LANDING_ADMIN_LOGIN_ENABLED=true
RATE_LIMIT_LANDING_ADMIN_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_LANDING_ADMIN_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_LANDING_ADMIN_LOGIN_KEY_STRATEGY=username_ip
```

## Changed files

- `app/Support/ActiveLoginSession.php`
- `app/Http/Middleware/SessionTimeout.php`
- `app/Http/Controllers/Auth/SessionController.php`
- `app/Http/Controllers/Landing/LandingAdminAuthController.php`
- `app/Http/Responses/AccountingLoginResponse.php`
- `app/Providers/FortifyServiceProvider.php`
- `app/Providers/AppServiceProvider.php`
- `app/Models/User.php`
- `app/Models/LandingAdminUser.php`
- `app/Actions/Fortify/ResetUserPassword.php`
- `routes/web.php`
- `config/session.php`
- `resources/views/layouts/accounting.blade.php`
- `resources/views/layouts/landing-admin.blade.php`
- `resources/views/livewire/auth/login.blade.php`
- `resources/views/landing/admin/login.blade.php`
- `public/js/hisebghor-session-timeout.js`
- `database/migrations/2026_06_18_100000_add_active_session_id_to_auth_tables.php`
