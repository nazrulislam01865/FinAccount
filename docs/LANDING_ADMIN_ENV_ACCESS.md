# Landing Page Admin Access

Landing Page Admin now uses a separate login guard and a separate database table from the accounting system.

## Direct URLs

- Public landing page: `/`
- Demo/system login: `/login`
- Landing Admin login/dashboard: `/landing-admin`
- Landing Admin editor: `/landing-admin/page?section=basic`

The root URL `/` always opens the public landing page. Its header provides separate buttons for `/login` and `/landing-admin`, while demo/contact buttons continue to use the configured landing-page destination.

## Environment variables

Add these values to the server `.env` file:

```env
LANDING_ADMIN_SEED_ENABLED=true
LANDING_ADMIN_NAME=Landing Page Admin
LANDING_ADMIN_USERNAME=landingadmin
LANDING_ADMIN_EMAIL=landingadmin@hisebghor.test
LANDING_ADMIN_PASSWORD=LandingAdmin@12345
```

Use a strong password in production and change the example username, email, and password before deployment.

## Create/update the Landing Admin user

After adding the values to `.env`, run:

```bash
php artisan migrate --force
php artisan config:clear
php artisan db:seed --class=LandingAdminUserSeeder --force
php artisan optimize:clear
```

## Where the credentials are stored

- The source credential values are read from `.env` during seeding.
- The landing admin account is stored in `landing_admin_users`.
- The landing admin password is stored as a Laravel hash in `landing_admin_users.password`.
- It is not assigned through `roles`, `role_user`, or `permissions`.
- Normal accounting users remain stored in `users`.
- Landing page content remains stored in `landing_page_settings`.
- Landing page inquiries remain stored in `landing_page_inquiries`.

## Separation rules

- `/login` uses the normal Laravel `web` guard for demo/accounting users.
- `/landing-admin` uses the dedicated `landing_admin` guard and authenticates with the `.env` username and password.
- Normal Super Admin credentials do not automatically open the landing admin dashboard.
- Landing admin credentials do not open the accounting dashboard.
- The landing admin dashboard does not link to the accounting dashboard.
