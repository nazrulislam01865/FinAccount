# HisebGhor — Template-Matched Four Pages

This build keeps the first four accounting pages aligned with the supplied HTML demo while using Laravel, Blade, controllers, services, requests and MySQL.

## Implemented pages

1. Dashboard
2. Transaction Entry
3. Transaction Register
4. Chart of Accounts

## Important UI behavior

- Chart of Accounts Add/Edit opens in the same page modal.
- There are no separate COA create/edit pages or routes.
- Transaction edit reuses the same Transaction Entry Blade page.
- Transaction Register remains the same table layout as the demo.
- Search and filters use normal Laravel GET requests.
- Only small JavaScript files are used for journal preview and COA modal opening/population.

## Setup

```bash
composer install
npm install --ignore-scripts

mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache

php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=HisebGhorDemoSeeder
npm run build
composer run dev
```

For a completely empty development database only:

```bash
php artisan migrate:fresh --seed
```

Do not use `migrate:fresh` when preserving existing data.

## Demo login

- Email: `admin@hisebghor.test`
- Password: `password`
