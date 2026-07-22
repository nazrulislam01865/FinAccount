# Bashir Agro Dynamic Website CMS

A modular Laravel website and section-based administration panel built from the supplied Bashir Agro template. The public layout remains unchanged while the visible content, branding, navigation and media are database-driven.

## Included administration controls

- Uploaded website logo used in the public header, footer, admin sidebar and login screen
- Dynamic header and footer navigation manager
- Menu label, target section, visibility and arrow-based menu positioning
- Hero content and background slider
- Image-backed statistics cards
- About story and dynamic key points
- Focus area cards
- Image-backed mission, vision and values cards
- Owner message and portrait
- Gallery and lightbox images
- Contact details, map and uploaded contact images
- Wholesale call-to-action banner
- Site metadata and footer content
- Secure administrator login at `/admin/login`
- Username or email sign-in with login throttling
- Administrator credentials seeded from `.env`

Display-order number inputs are intentionally not shown in the admin forms. Content uses stable internal ordering, and navigation position is controlled with simple arrow buttons.

## Installation

```bash
cp .env.example .env
composer install
php artisan key:generate
```

Create a MySQL database and configure the `DB_*` values in `.env`. Also set the administrator credentials:

```env
ADMIN_NAME="Bashir Agro Admin"
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@bashiragro.com
ADMIN_PASSWORD="YourStrongPassword123!"
```

Run:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Public website:

```text
http://127.0.0.1:8000
```

Administrator login:

```text
http://127.0.0.1:8000/admin/login
```

## Updating an existing installation

Back up the database and uploaded files, replace the project files, then run:

```bash
php artisan migrate
php artisan storage:link
php artisan optimize:clear
npm install
npm run build
```

The new migration adds the logo setting, navigation manager, image fields for statistics and direction cards, and uploaded contact images. Existing content is retained.

## Updating the logo

Open:

```text
Admin Panel → Site & Footer → Website Logo
```

Upload a transparent horizontal PNG, JPG or WebP image. The uploaded logo replaces the old icon and brand text throughout the website and admin authentication screens.

## Managing navigation

Open:

```text
Admin Panel → Navigation Menu
```

You can:

- Change the visible menu label
- Choose the linked homepage section
- Show or hide a menu item
- Move links up or down without entering technical order numbers
- Remove or restore section links

The same active navigation links appear in the public header and footer.

## Updating administrator credentials

After changing any `ADMIN_*` value in `.env`, run:

```bash
php artisan config:clear
php artisan db:seed --class=AdminUserSeeder
```

The existing administrator is updated instead of creating a duplicate account.

## XAMPP / Apache usage

When the project is inside XAMPP `htdocs`, the included root `.htaccess` forwards requests to Laravel's `public` directory without adding `/public` to the URL.

Example:

```env
APP_URL=http://localhost/laravel/Bashir-Agro-1
```

Open:

```text
http://localhost/laravel/Bashir-Agro-1/admin/login
```

Ensure Apache `mod_rewrite` is enabled and `AllowOverride All` is permitted for `htdocs`.

## Production notes

```bash
php artisan optimize
npm run build
```

Use a strong administrator password, set `APP_DEBUG=false`, configure the production database, and ensure `storage` and `bootstrap/cache` are writable by the web server.

## Central branding and favicon

Open **Admin → Site & Footer Settings → System Branding** to upload:

- **Main website logo:** used in the public header/footer, admin sidebar, and admin login screen.
- **Browser favicon:** used in browser tabs, bookmarks, shortcuts, and supported mobile home-screen icons.

When no dedicated favicon is uploaded, the current main logo is used as the favicon automatically.
After updating the project, run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan storage:link
npm install
npm run build
```
