# Landing Admin Separate Access

Landing Page Admin is intentionally separated from the accounting system login.

## URLs

- Public landing page: `/`
- Demo/system login: `/login`
- Landing admin login/dashboard: `/landing-admin`

The public landing page demo buttons must redirect to `/login` only.
Landing admin access is URL-only through `/landing-admin`.

## Storage

Normal system users are stored in:

```text
users
```

Landing page admin users are stored separately in:

```text
landing_admin_users
```

The landing admin password is hashed in `landing_admin_users.password`.
The `.env` values are only used by the seeder to create/update the account.

## .env

```env
LANDING_ADMIN_SEED_ENABLED=true
LANDING_ADMIN_NAME=Landing Page Admin
LANDING_ADMIN_EMAIL=landingadmin@example.com
LANDING_ADMIN_PASSWORD=LandingAdmin@12345
```

## Deployment commands

```bash
php artisan migrate --force
php artisan db:seed --class=LandingAdminUserSeeder --force
php artisan optimize:clear
```

## Separation rules

- `/login` authenticates the normal `web` guard and sends users to the accounting dashboard.
- `/landing-admin` authenticates the `landing_admin` guard and sends users to the landing dashboard.
- The Landing Admin dashboard does not show the Accounting Dashboard link.
- Normal Super Admin credentials do not automatically open `/landing-admin`; use the dedicated Landing Admin credentials.
