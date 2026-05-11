# Sprint 1 Accounting System - Frontend + Backend (Master Setup UI Removed)

This package contains the Sprint 1 code for the Laravel accounting system.

Master Setup menu/pages are removed from the UI for now, but the backend still seeds and uses the master tables so dropdowns remain dynamic.

## Included modules

- Company Setup
- Chart of Accounts
- Cash / Bank Setup
- Party / Person Setup
- Transaction Head Setup
- Users & Roles foundation
- Dynamic dropdown APIs
- Master data seeders for business types, currencies, time zones, account types, party types, banks, settlement types

## Copy order

After installing Laravel Breeze, copy these folders/files into the project root:

```text
app/
database/
resources/
routes/web.php
routes/api.php
```

## Install from a clean Laravel project

```bash
composer create-project laravel/laravel accounting-system
cd accounting-system
cp .env.example .env
php artisan key:generate
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
npm run build
```

Now copy this package into the Laravel root, then run:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate:fresh --seed
npm install axios
npm run build
php artisan serve
```

Login:

```text
Email: admin@example.com
Password: password
```

## Pages

```text
/dashboard
/setup/company
/setup/chart-of-accounts
/setup/cash-bank-accounts
/setup/parties
/setup/transaction-heads
/settings/users-roles
```

## Dynamic dropdown APIs

```text
GET /api/dropdowns/business-types
GET /api/dropdowns/currencies
GET /api/dropdowns/time-zones
GET /api/dropdowns/account-types
GET /api/dropdowns/parent-accounts
GET /api/dropdowns/party-types
GET /api/dropdowns/banks
GET /api/dropdowns/cash-bank-ledgers
GET /api/dropdowns/ledger-accounts
GET /api/dropdowns/settlement-types
```

## Save APIs used by the frontend

```text
POST /api/company
POST /api/chart-of-accounts
POST /api/cash-bank-accounts
POST /api/parties
POST /api/transaction-heads
POST /api/users
```

## Notes

- Do not add the Master Setup UI yet.
- The tables still exist because dropdown data must be dynamic.
- Use seeders to maintain business types, currencies, time zones, account types, party types, banks, and settlement types for now.
