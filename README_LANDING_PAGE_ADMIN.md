# HisebGhor Landing Page and Landing Admin

The public landing page and separate landing-admin module were transferred from the Finacco project without replacing the existing HisebGhor accounting modules or system login.

## URLs

- Public landing page: `/`
- Public landing alias: `/landing`
- Existing accounting login: `/login`
- Separate landing-admin login: `/landing-admin`
- Landing-admin dashboard: `/landing-admin/dashboard`
- Landing-page editor: `/landing-admin/page`


## Included local Landing Admin credentials

The supplied local `.env` contains:

```text
Username: landingadmin
Password: HisebGhor@Landing2026
```

Change these two `.env` values before production deployment, then clear configuration and rerun the Landing Admin seeder.

## First deployment

Add these values to the server `.env` file:

```env
LANDING_ADMIN_SEED_ENABLED=true
LANDING_ADMIN_NAME="Landing Page Admin"
LANDING_ADMIN_USERNAME=landingadmin
LANDING_ADMIN_EMAIL=landingadmin@hisebghor.test
LANDING_ADMIN_PASSWORD="Use-A-Strong-Password-Here"
LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT=15
RATE_LIMIT_LANDING_INQUIRY_PER_MINUTE=5
PERF_LANDING_PAGE_CACHE_TTL=86400

RATE_LIMIT_LANDING_ADMIN_LOGIN_ENABLED=true
RATE_LIMIT_LANDING_ADMIN_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_LANDING_ADMIN_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_LANDING_ADMIN_LOGIN_KEY_STRATEGY=username_ip
```

The Landing Admin signs in with `LANDING_ADMIN_USERNAME` and `LANDING_ADMIN_PASSWORD`. The password must contain at least 12 characters. `LANDING_ADMIN_EMAIL` is retained as account metadata and does not replace the username login.

Run:

```bash
php artisan migrate --force
php artisan db:seed --class=LandingAdminUserSeeder --force
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Separation rules

- The public landing page opens by default at `/`. Normal HisebGhor users continue to sign in through `/login`.
- The public header contains separate System Login and Landing Admin buttons. Landing managers sign in only through `/landing-admin`.
- The dedicated account is stored in `landing_admin_users`.
- Landing content is stored in `landing_page_settings`.
- Demo/contact requests are stored in `landing_page_inquiries`.
- Landing images uploaded by an admin are stored under `public/uploads/landing`.
