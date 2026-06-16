# HisebGhor Template Backend Parity Audit

## Scope and source of truth

The accounting backend in this build is matched against `hisebghor_sales_payment_liability_mvp_full_crud.html`.

Any other supplied project/template may be used only for reusable presentation components. No accounting rules, posting behavior, validation, relationships, balances, voucher logic, or report logic were copied from that secondary source.

## Module parity

| Template module | Laravel implementation | Backend behavior |
|---|---|---|
| Dashboard | `DashboardController` + `DashboardService` | Sales, payment, liability activity, money balance, seven recent transactions, company-specific sample reset |
| Transaction Entry | `TransactionEntryController` + `TransactionPostingService` | Category/head selection, dynamic required fields, automatic journal preview, idempotent posting, automatic voucher number |
| Transaction Register | `TransactionRegisterController` | Search, category filter, edit/repost, delete transaction with derived journal, CSV export |
| Chart of Accounts | `ChartOfAccountController` + `ChartOfAccountService` | Full CRUD, company code uniqueness, live balance, protected deletion |
| Money Accounts | `MoneyAccountController` + `MoneyAccountService` | Cash/bank/digital account CRUD, Asset COA mapping, opening/current balance, protected deletion |
| Parties | `PartyController` + `PartyService` | Customer/supplier/worker/owner/lender CRUD, receivable/payable mapping, live party balance |
| Accounting Rules | `AccountingRuleController` + `AccountingRuleService` | Debit and credit source setup, required money/party enforcement, party-type enforcement |
| Transaction Heads | `TransactionHeadController` + `TransactionHeadService` | Category-matched rule, posting COA, active setup consistency |
| Journal Entries | `JournalEntryController` + `JournalEntryService` | Stored system-generated debit/credit lines in voucher/sequence order |
| Balances | `BalanceController` + balance services | COA and party balances from opening values plus posted journal movement |
| Basic Statements | `BasicStatementController` + `BasicStatementService` | Income, expense, profit/loss, assets, liabilities, equity, money balance, collected sales, payments made |

## Database-backed dropdown domains

All accounting dropdown domains are stored in `accounting_options`, loaded through `AccountingOptionService`, rendered from database records, and validated against active database records.

| Option group | Template values |
|---|---|
| `account_type` | Asset, Liability, Income, Expense, Equity |
| `normal_balance` | Debit, Credit |
| `money_account_kind` | Cash, Bank, Digital |
| `party_type` | Customer, Supplier, Worker, Owner, Lender |
| `rule_party_type` | Any, Customer, Supplier, Worker, Owner, Lender |
| `transaction_category` | Sales, Payment, Liability |
| `accounting_source` | Selected Money Account, Transaction Head COA, Party Receivable COA, Party Payable COA |

The migration inserts these records automatically. The seeder is idempotent and restores missing/default records. Transaction-category metadata supplies the voucher prefix and money-field label. A new active database category can create its document sequence automatically when first posted.

## Exact template seed dataset

The demo seeder reproduces the template data:

- 15 Chart of Accounts records
- 3 Money Accounts
- 6 Parties
- 7 Accounting Rules
- 9 Transaction Heads
- 7 Transactions
- 7 Journal Entries and 14 Journal Lines

Expected sample totals:

| Measure | Amount |
|---|---:|
| Total Sales | 6,700.00 |
| Payments | 6,000.00 |
| Liability Activity | 63,000.00 |
| Money Balance | 74,500.00 |
| Income | 6,700.00 |
| Expenses | 11,000.00 |
| Net Profit / Loss | -4,300.00 |
| Assets | 78,700.00 |
| Liabilities | 50,000.00 |
| Equity including profit/loss | -4,300.00 |
| Sales Collected | 2,500.00 |
| Payments Made | 11,000.00 |

## Posting rules matched

| Template activity | Debit | Credit |
|---|---|---|
| Immediate sale | Selected Money Account COA | Transaction Head COA |
| Credit sale | Party Receivable COA | Transaction Head COA |
| Expense payment | Transaction Head COA | Selected Money Account COA |
| Supplier due payment | Party Payable COA | Selected Money Account COA |
| Credit purchase | Transaction Head COA | Party Payable COA |
| Loan received | Selected Money Account COA | Party Payable COA |
| Loan repayment | Party Payable COA | Selected Money Account COA |

Journal-line money and party references are assigned from the configured rule source, not guessed from an equal account ID. This keeps party movement and money movement identical to the template rule semantics.

## Preservation of previous project work

A source-file inventory comparison against the supplied archive found no original application source file removed. Changes are additions or targeted updates to the existing accounting implementation; the landing-page module, authentication, settings, passkeys, layouts, and prior project files remain present. Generated Laravel caches are intentionally excluded from the delivery so the target server can rebuild them for its own environment.

## Additional backend safeguards

The Laravel implementation keeps the template behavior and adds production safeguards:

- Every accounting query and write is scoped to the authenticated user's company.
- Voucher generation uses a locked per-company/per-category sequence.
- Repeated submissions use a unique request token and do not create duplicate transactions.
- Posting, journal creation, transaction edits, deletes, and sample reset use database transactions with deadlock retries.
- Debit and credit must resolve to two different active company accounts.
- Required money account, required party, exact party type, category/rule/head consistency, and company ownership are enforced server-side.
- Editing a transaction rebuilds its two journal lines.
- Deleting a transaction removes its derived journal entry and lines.
- Setup records used by transactions, mappings, heads, or journals cannot be deleted unsafely.
- Existing posted journals remain stored accounting history; changing setup does not silently rewrite old entries. Editing the transaction explicitly recalculates its journal, matching the template's edit behavior.
- Sample reset is atomic and affects only the logged-in user's company.

## Verification included in the project

`tests/Feature/Accounting/AccountingTemplateParityTest.php` verifies:

- Exact template master data and transactions
- Every dropdown domain in the database
- Runtime rendering and acceptance of a database-added option
- Exact debit/credit pairs for all seven sample vouchers
- Optional-party retention without false party movement
- Rule-source-based journal references
- Exact dashboard and statement totals
- Category/head and party-type validation
- Transaction edit journal rebuild
- Transaction/journal deletion
- Used-COA deletion protection
- Automatic voucher sequence creation for a database-added category
- Migration-only installation of all 28 required database option records
- Company-scoped reset and cross-company access protection

Additional setup and posting tests remain in:

- `tests/Feature/Accounting/TransactionPostingTest.php`
- `tests/Feature/AccountingSetupPagesTest.php`

## Deployment

```bash
php artisan down || true
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

To load the exact sample dataset intentionally:

```bash
php artisan db:seed --class=HisebGhorDemoSeeder --force
```

Do not run the demo seeder on a company whose real records should be preserved.

## Validation status for this delivered archive

- PHP syntax validation: passed for all 139 PHP files in the delivered codebase.
- Frontend production build: passed.
- npm dependency audit: zero reported vulnerabilities.
- PHPUnit/Laravel execution could not run in the packaging environment because its PHP CLI lacks `mbstring`, `xml`, `dom`, `xmlwriter`, and PDO database drivers. The complete test suite is included and is configured for SQLite in-memory testing. Run it in the deployment/development PHP environment after enabling the normal Laravel test extensions.
