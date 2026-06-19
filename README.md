# HisebGhor

HisebGhor is a Laravel-based accounting and business management application for small and medium businesses. It combines a public landing page, a separate landing-page admin panel, a secure system login, company setup, master data, accounting rules, transaction posting, journal generation, sales invoice generation, ledger reporting, due management, dashboard summaries, role-based access control, and real-time-style notifications.

The accounting engine follows a journal-first design: every posted transaction creates balanced journal lines, and reports such as ledger, trial balance, due report, balance sheet, and income statement are calculated from those journal lines.

---

## Core Features

### Public Landing Page

- Responsive landing page for public visitors.
- Landing-page inquiry/contact form with rate limiting.
- Separate Landing Admin login at `/landing-admin`.
- Landing Admin can update landing page content, images, FAQ, pricing, testimonials, audience sections, and inquiries.
- Mobile responsive navigation so login and landing-admin buttons do not overlap on small devices.

### Authentication and Session Security

- Laravel Fortify based authentication.
- System login for accounting users.
- Separate landing-admin authentication guard.
- One active session per user/admin account.
- New login automatically invalidates the previous active session.
- Server-side inactivity logout.
- Login rate limiting configurable from `.env`.
- Profile page with profile image and password change.

### Company Setup

- Company profile and setup workflow.
- Company code, legal name, short name, business type, status, address, phone, email, website, trade license, BIN/VAT, TIN, currency, time zone, and financial year.
- Active company setup is required before posting transactions.
- Financial year and lock-date validation for accounting transactions.
- Currency precision is applied to amount inputs and reports.
- Company branding logo and favicon are controlled through system settings.

### Master Data

The system includes reusable master data for:

- Business Types
- Currencies
- Time Zones
- Financial Years
- Party Types
- Money Account Types
- Transaction Categories
- Voucher Numbering

### Accounting Setup

- Chart of Accounts
- Money Accounts mapped to COA ledgers
- Parties mapped to receivable/payable COA ledgers
- Accounting Rules
- Transaction Heads
- Voucher sequences

### Accounting Rules

Accounting rules define how transaction entries are converted into journal lines.

The simplified rule form includes:

- Code
- Name
- Category
- Party Type
- Debit Source
- Credit Source
- Allow partial payment / due split
- Generate sales invoice
- Invoice title
- Money account required
- Party required
- Active status

The frontend stays simple, while the backend maintains rule-line logic for proper posting.

### Rule-Based Split Transaction

Split transaction is rule-based, not manually selected in Transaction Entry.

Example partial sale:

```text
Dr Selected Money Account      Paid Amount
Dr Party Receivable COA        Due Amount
    Cr Sales Income            Total Amount
```

Example partial purchase:

```text
Dr Purchase / Expense COA      Total Amount
    Cr Selected Money Account  Paid Amount
    Cr Party Payable COA       Due Amount
```

If a selected Transaction Head uses a split-enabled rule, Transaction Entry automatically shows Paid Amount, Due Amount, and Due Date fields.

### Transaction Entry

- Category-based transaction entry.
- Transaction Head loads from database.
- Transaction Head controls required fields through its accounting rule.
- Money Account comes from Money Accounts.
- Party comes from Parties.
- Automatic journal preview before saving.
- Server-side debit/credit account resolution.
- Duplicate request-token protection.
- Attachment support for receipt/image/reference files.
- On mobile/tablet, attachment input supports camera capture for receipt images.

### Sales Invoice

- Sales invoices are generated from posted sales transactions.
- Invoice generation is controlled by the selected Accounting Rule.
- Only Sales category rules can generate invoices.
- Invoice does not create journal entries; it is a sales document linked with the posted transaction.
- Invoice statuses: Paid, Partial, Unpaid.
- Invoice page has Print / Save PDF and Download Invoice buttons.
- Transaction Register shows Invoice and Download buttons for generated invoices.
- Sales invoice can automatically download after posting a sales transaction when the rule is invoice-enabled.

### Reports and Due Management

- Trial Balance
- Ledger Report
- Due Report
- Due Management
- Balance Sheet
- Income Statement
- Account balances
- Party balances
- Money account balances

Due Management settles receivables/payables by posting normal accounting transactions through the same journal engine.

### Dashboard

- Accounting dashboard with summary cards.
- Recent transactions and activity sections.
- Financial and operational summaries.
- Company-scoped data.

### Notifications

- Notification center with unread count.
- Database-backed notifications.
- Pusher Channels support for real-time delivery.
- Polling fallback when Pusher is not configured.
- Notifications for create, update, delete, branding, user, role, and company setup actions.

### Role Matrix and User Management

- Fleet-style role matrix.
- View and Manage permissions per module.
- Delete permission separated as a dedicated permission.
- System users can be Active, Inactive, Disabled, or Standby.
- Disabled users cannot access the system.
- Role-based menus and module access.
- Role Matrix layout follows normal page height without extra blank footer space.

---

## Technology Stack

- PHP `^8.3`
- Laravel `^13.7`
- Laravel Fortify
- Livewire
- Flux UI / Livewire starter kit base
- MySQL / MariaDB
- Vite
- Tailwind CSS
- JavaScript modules for page-specific behavior
- Optional Pusher Channels integration

---

## Project Structure

```text
app/
  Http/
    Controllers/
      Accounting/
      Landing/
      Auth/
    Middleware/
    Requests/
  Models/
  Services/
    Accounting/
    Company/
    Dashboard/
    Notifications/
  Support/

config/
  auth.php
  database.php
  landing.php
  landing_admin.php
  performance.php
  security.php
  services.php
  session.php

database/
  migrations/
  seeders/

resources/
  css/
  js/
  views/
    accounting-rules/
    chart-of-accounts/
    company-setup/
    dashboard/
    landing/
    money-accounts/
    parties/
    reports/
    sales-invoices/
    system/
    transaction-heads/
    transactions/

routes/
  web.php
  settings.php
```

---

## Main Modules and Routes

| Module | Main URL |
|---|---|
| Public Landing Page | `/` |
| Landing Admin Login | `/landing-admin` |
| Landing Admin Dashboard | `/landing-admin/dashboard` |
| System Login | `/login` |
| Dashboard | `/dashboard` |
| Transaction Register | `/transactions` |
| Transaction Entry | `/transactions/create` |
| Chart of Accounts | `/chart-of-accounts` |
| Money Accounts | `/money-accounts` |
| Parties | `/parties` |
| Accounting Rules | `/accounting-rules` |
| Transaction Heads | `/transaction-heads` |
| Journal Entries | `/journal-entries` |
| Sales Invoice | `/sales-invoices/{salesInvoice}` |
| Trial Balance | `/reports/trial-balance` |
| Ledger Report | `/reports/ledger-report` |
| Due Report | `/reports/due-report` |
| Due Management | `/reports/due-management` |
| Balance Sheet | `/reports/balance-sheet` |
| Income Statement | `/reports/income-statement` |
| Company Setup | `/company-setup` |
| Role Matrix | `/system/role-matrix` |
| Users | `/system/users` |
| Branding Settings | `/system/settings` |

---

## Accounting Flow

```text
Company Setup
    ↓
Master Data
    ↓
Chart of Accounts
    ↓
Money Accounts / Parties
    ↓
Accounting Rules
    ↓
Transaction Heads
    ↓
Transaction Entry
    ↓
Journal Entry + Journal Lines
    ↓
Reports / Ledger / Trial Balance / Due Management
```

For invoice-enabled sales rules:

```text
Sales Transaction Posted
    ↓
Journal Entry Created
    ↓
Sales Invoice Created / Updated
    ↓
Invoice View / Download / Print
```

---

## Local Installation

### 1. Clone or extract the project

```bash
cd /path/to/project
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Create environment file

If `.env.example` exists:

```bash
cp .env.example .env
```

If not, create `.env` manually using the important variables shown below.

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Create database

Example MySQL command:

```sql
CREATE DATABASE hisebghor
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 7. Configure `.env`

```env
APP_NAME="HisebGhor"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Dhaka

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hisebghor
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_INACTIVE_TIMEOUT=15
LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT=15

CACHE_STORE=database
QUEUE_CONNECTION=database

LANDING_ADMIN_SEED_ENABLED=true
LANDING_ADMIN_NAME="Landing Page Admin"
LANDING_ADMIN_USERNAME=landingadmin
LANDING_ADMIN_EMAIL=landingadmin@example.com
LANDING_ADMIN_PASSWORD=change-this-secure-password

RATE_LIMIT_SYSTEM_LOGIN_ENABLED=true
RATE_LIMIT_SYSTEM_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_SYSTEM_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_SYSTEM_LOGIN_KEY_STRATEGY=email_ip

RATE_LIMIT_LANDING_ADMIN_LOGIN_ENABLED=true
RATE_LIMIT_LANDING_ADMIN_LOGIN_MAX_ATTEMPTS=5
RATE_LIMIT_LANDING_ADMIN_LOGIN_LOCK_MINUTES=120
RATE_LIMIT_LANDING_ADMIN_LOGIN_KEY_STRATEGY=username_ip

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap2
PUSHER_HOST=
```

### 8. Prepare runtime folders

```bash
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         bootstrap/cache

chmod -R u+rwX,g+rwX storage bootstrap/cache
```

### 9. Run migrations and seeders

```bash
php artisan migrate --seed
```

For a fresh demo reset:

```bash
php artisan migrate:fresh --seed
```

### 10. Build frontend assets

```bash
npm run build
```

### 11. Start local server

```bash
php artisan serve
```

Open:

```text
http://localhost:8000
```

---

## Default Demo Login

The demo seeder creates a system user:

```text
Email: admin@hisebghor.test
Password: password
```

Landing Admin is seeded from `.env`:

```text
URL: /landing-admin
Username: value of LANDING_ADMIN_USERNAME
Password: value of LANDING_ADMIN_PASSWORD
```

Use a strong Landing Admin password. The seeder requires the configured password to be at least 12 characters.

---

## Development Commands

Run all local development processes:

```bash
composer run dev
```

Run only Laravel:

```bash
php artisan serve
```

Run only Vite:

```bash
npm run dev
```

Build production frontend assets:

```bash
npm run build
```

Format PHP code:

```bash
composer run lint
```

Check formatting:

```bash
composer run lint:check
```

Run PHPStan:

```bash
composer run types:check
```

Run tests/checks:

```bash
composer run test
```

---

## Production Deployment

A safe deployment flow:

```bash
cd /var/www/hisebghor

php artisan down || true

git fetch origin main
git checkout main
git reset --hard origin/main

mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;

COMPOSER_ALLOW_SUPERUSER=1 composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction

npm install
npm run build

php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

Do not overwrite the production `.env` during deployment. Keep a backup before pulling code.

---

## Important Environment Notes

### Pusher notifications

Pusher is optional. If configured, set:

```env
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=ap2
PUSHER_HOST=
```

If Pusher is not configured, the notification center still works with polling fallback.

### Session and rate limit security

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

### Security headers

Security headers are controlled in `config/security.php` and can be toggled with:

```env
SECURITY_HEADERS_ENABLED=true
SECURITY_CSP_ENABLED=true
SECURITY_HSTS_ENABLED=false
```

Enable HSTS only after HTTPS is correctly configured.

---

## Database Design Summary

Important tables include:

- `companies`
- `users`
- `landing_admin_users`
- `landing_page_settings`
- `landing_page_inquiries`
- `business_types`
- `currencies`
- `time_zones`
- `financial_years`
- `accounting_options`
- `chart_of_accounts`
- `money_accounts`
- `parties`
- `accounting_rules`
- `accounting_rule_lines`
- `transaction_heads`
- `document_sequences`
- `transactions`
- `transaction_attachments`
- `journal_entries`
- `journal_lines`
- `sales_invoices`
- `accounting_roles`
- `accounting_permissions`
- `form_drafts`

---

## Accounting Principles Used

- Every posted transaction must balance total debit and total credit.
- Ledgers and reports are generated from `journal_lines`.
- Sales invoices are linked business documents and do not affect ledgers directly.
- Receivables and payables are generated through Party Receivable and Party Payable COA mappings.
- Partial payment / due split is controlled by Accounting Rule setup.
- Transaction dates are validated against company financial year status and lock date.
- Voucher numbers are generated through controlled voucher/document sequences.

---

## Recommended Setup Order for a New Company

1. Login as system admin.
2. Complete Company Setup.
3. Configure Business Types, Currency, Time Zone, and Financial Year.
4. Configure Chart of Accounts.
5. Configure Money Accounts.
6. Configure Parties with receivable/payable COA mappings.
7. Configure Accounting Rules.
8. Configure Transaction Heads.
9. Configure Voucher Numbering.
10. Post transactions from Transaction Entry.
11. Review Journal Entries, Reports, Due Management, and Invoices.

---

## Troubleshooting

### `Please provide a valid cache path`

Create Laravel runtime directories:

```bash
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache
php artisan optimize:clear
```

### Frontend build native package error

If Vite/Rollup/Rolldown native bindings are missing:

```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

Or keep `package-lock.json` and run:

```bash
npm ci
npm run build
```

### Permission issue after deployment

```bash
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
php artisan optimize:clear
```

### Login works on one browser but logs out another

This is expected. The application enforces one active session per user or landing admin account.

### Invoice not generating

Check these conditions:

1. Transaction category is `Sales`.
2. Selected Transaction Head uses an Accounting Rule with `Generate sales invoice` enabled.
3. Transaction status is `posted`.
4. The user has permission to view/manage transactions.

---

## Notes for Future Development

Recommended future improvements:

- Add downloadable server-side PDF generation using DomPDF or Browsershot if browser print is not enough.
- Add itemized invoice lines if product/service inventory is introduced.
- Add tax and discount setup if VAT/TAX automation is required.
- Add automated backup and restore commands.
- Add more feature tests for sales invoice, rule-based split posting, and due settlement.

---

## License

This project is private/proprietary unless a license file is added by the owner.
