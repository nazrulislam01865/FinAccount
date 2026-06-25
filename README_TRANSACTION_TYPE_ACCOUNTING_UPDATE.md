# HisebGhor Transaction-Type Accounting Update

## What changed

HisebGhor now chooses the accounting rule automatically from:

```text
Transaction Type + Payment Type
```

The transaction head only identifies the business head, linked COA account, allowed payment types, and expected party type. Users no longer select an accounting rule during transaction entry or transaction-head setup.

## Minimal database changes

No separate `transaction_types` or `settlement_types` tables were added because the project already has the reusable `accounting_options` master.

Only these necessary fields were added:

```text
accounting_rules.settlement_type
transaction_heads.allowed_settlements
transaction_heads.party_type
```

The existing fields below are reused:

```text
transactions.category          -> Transaction Type code
transactions.settlement_type   -> CASH / CREDIT / PARTIAL
transactions.paid_amount
transactions.due_amount
transactions.due_date
transaction_heads.posting_account_id
```

The old `transaction_heads.accounting_rule_id` column remains temporarily for safe backward compatibility, but the migration clears existing links and all new/updated heads save it as `null`.

## Transaction types

- Sale
- Purchase
- Customer Collection
- Supplier Payment
- Expense
- Owner Investment
- Owner Withdrawal
- Loan Received
- Loan Repayment
- Loan Interest Payment
- Asset Purchase

## Payment types

- Paid/received in full (`CASH`)
- Fully due (`CREDIT`)
- Part paid, remaining due (`PARTIAL`)

## New transaction flow

```text
What happened?
→ What was it?
→ How was payment handled?
→ Amount / Paid now / Remaining due
→ Customer, supplier, owner or lender when required
→ Cash / bank / mobile account when required
→ Automatic journal
```

Example:

```text
Sale → Milk Sale → Partial
Total: 10,000
Received now: 4,000
Customer: Rahim Dairy
Received in: Cash
```

Automatic journal:

```text
Cash                         Dr 4,000
Customer Receivable          Dr 6,000
Sales Income                 Cr 10,000
```

## Updated modules

- Transaction Entry
- Transaction Register and edit flow
- Accounting Rule Templates
- Transaction Heads
- Due Management
- Sales invoice generation
- Dashboard and basic statement category logic
- Voucher numbering labels
- Master Transaction Types
- Demo data and feature tests

## Existing features retained

- Double-entry journal storage
- Balanced debit/credit validation
- Partial transaction logic
- Voucher numbering
- Financial-period validation
- Drafts
- Attachments and mobile camera capture
- Due reports
- Ledger, trial balance, statements, and cash flow
- Automatic sales invoice download
- Role and permission system
- Safe delete workflows

## Existing-data migration

The migration:

1. Adds the three required fields.
2. Seeds the fixed transaction and payment types.
3. Converts legacy Sales/Payment/Liability rules to business transaction types.
4. Converts existing heads to transaction-type heads.
5. Clears direct head-to-rule binding.
6. Converts old transaction settlement values to CASH/CREDIT/PARTIAL.
7. Creates missing standard rule templates and voucher sequences.
8. Keeps historical transactions, journal entries, and journal lines.

Used legacy heads are not deleted.

## Production deployment

Back up the project and database first.

```bash
cd /var/www/hisebghor

php artisan down
php artisan optimize:clear

php artisan migrate --force

npm ci
npm run build

sudo chown -R www-data:www-data /var/www/hisebghor
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 755 public/build

sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart

sudo systemctl restart php8.4-fpm
sudo nginx -t
sudo systemctl reload nginx

php artisan up
```

Do not run `HisebGhorDemoSeeder` on an existing production database because it contains demonstration master data and transactions.

## Fresh local installation

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Demo login remains the one configured by the existing demo seeder.

## Validation completed

- 227 PHP files passed syntax validation.
- 103 Blade templates compiled and passed PHP syntax validation.
- 115 Laravel routes loaded successfully.
- Vite production build completed successfully.
- Feature tests were updated for automatic cash, credit, and partial rule matching.

The full PHPUnit run could not be executed in the packaging environment because its PHP CLI does not include DOM, mbstring, XML, XMLWriter, or a PDO database driver. Run the tests in the normal project/server environment with those extensions enabled:

```bash
php artisan test
```
