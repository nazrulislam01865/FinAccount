# Login Rate Limiting and Countdown

This project uses failure-based login rate limiting for both login areas:

- System/accounting login: `/login`
- Landing Page Admin login: `/landing-admin`

The limiter counts only failed login attempts. A successful login clears the counter.

## Environment values

Add these values to `.env`:

```env
RATE_LIMIT_SYSTEM_LOGIN_ENABLED=true
RATE_LIMIT_SYSTEM_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_SYSTEM_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_SYSTEM_LOGIN_KEY_STRATEGY=email_ip

RATE_LIMIT_LANDING_ADMIN_LOGIN_ENABLED=true
RATE_LIMIT_LANDING_ADMIN_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_LANDING_ADMIN_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_LANDING_ADMIN_LOGIN_KEY_STRATEGY=email_ip
```

## Lock duration

For one hour:

```env
RATE_LIMIT_SYSTEM_LOGIN_LOCK_MINUTES=60
RATE_LIMIT_LANDING_ADMIN_LOGIN_LOCK_MINUTES=60
```

For two hours:

```env
RATE_LIMIT_SYSTEM_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_LANDING_ADMIN_LOGIN_LOCK_MINUTES=120
```

## Key strategy

Supported values:

```env
email_ip
email
ip
global
```

Recommended value:

```env
email_ip
```

This is safer for SME offices because it reduces the chance that one user blocks every user on the same office internet connection.

## Disable rate limiting

This is not recommended for production, but it is possible:

```env
RATE_LIMIT_SYSTEM_LOGIN_ENABLED=false
RATE_LIMIT_LANDING_ADMIN_LOGIN_ENABLED=false
```

## MAC address limitation

MAC-based login rate limiting is not possible in a normal web application because browsers do not send the user's MAC address to the Laravel server. The server can reliably use request-level identifiers such as email, IP address, session/cookie-based device marker, or authenticated user ID after login.

## Countdown behavior

When a login is locked, the login page shows a live browser countdown. The counter updates every second without refreshing the page. The submit button remains disabled until the timer reaches zero.

After changing `.env`, run:

```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```
