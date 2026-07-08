# Fully Automatic Report Setup Update

This update adds Finnacco-style report grouping to HisebGhor without showing report setup fields to normal users or admins.

## What changed

### 1. Automatic COA report setup
A new migration adds hidden report classification fields to `chart_of_accounts`:

- `report_section`
- `cash_flow_section`
- `is_cash_bank`
- `is_party_control`
- `is_posting`
- `sort_order`

Existing COA records are backfilled automatically during migration.

### 2. No user-selectable setup fields
The COA create/edit UI was not changed. Users do not select report sections manually.

The system automatically classifies accounts based on:

- account type
- account name keywords
- parent account section
- account level

### 3. Automatic classification examples

Income:

- Sales Income -> Revenue
- Service Income -> Revenue
- Interest Income -> Other Income

Expense:

- Purchase Cost -> Cost of Sales
- Salary Expense -> Operating Expense
- Office Expense -> Administrative Expense
- Advertisement Expense -> Selling Expense
- Bank Charge / Loan Interest -> Financial Expense
- Tax Expense -> Tax Expense

Asset:

- Cash / Bank / Receivable -> Current Asset
- Furniture / Equipment / Vehicle -> Fixed Asset

Liability:

- Supplier Payable -> Current Liability
- Long Term Loan -> Non Current Liability

Equity:

- Owner Capital -> Owner Capital
- Retained Earnings -> Retained Earnings

### 4. Model-level automation
`ChartOfAccount` now automatically recalculates hidden report fields on save, so direct seeders or future code paths also remain automatic.

### 5. Updated Income Statement
Income Statement now shows:

- Revenue
- Cost of Sales
- Gross Profit
- Operating Expenses
- Operating Profit
- Other Income
- Financial Expense
- Tax Expense
- Net Profit / Loss

### 6. Updated Balance Sheet
Balance Sheet now groups accounts by report sections:

- Current Asset
- Fixed Asset
- Current Liability
- Non Current Liability
- Owner Capital
- Equity
- Retained Earnings

### 7. Updated Trial Balance / Ledger source
Trial Balance and Ledger account selections now use posting ledgers through `is_posting`.

## Deployment note
Run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Then regenerate cache as usual.
