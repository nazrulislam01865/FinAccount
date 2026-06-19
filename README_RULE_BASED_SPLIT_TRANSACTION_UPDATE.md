# Rule-Based Split Transaction Update

This update removes the manual `Settlement Type` selector from Transaction Entry and moves split transaction behavior into Accounting Rule setup.

## Accounting principle

A transaction entry user should not decide whether journal lines are normal or split. The selected Transaction Head points to an Accounting Rule, and the Accounting Rule defines the debit/credit posting lines.

## Normal rule examples

### Cash Sale

| Side | Account Source | Amount Basis |
|---|---|---|
| Debit | Selected Money Account | Total Amount |
| Credit | Transaction Head COA | Total Amount |

Journal:

```text
Dr Cash/Bank              total
    Cr Sales Income       total
```

### Credit Sale

| Side | Account Source | Amount Basis |
|---|---|---|
| Debit | Party Receivable COA | Total Amount |
| Credit | Transaction Head COA | Total Amount |

Journal:

```text
Dr Customer Receivable    total
    Cr Sales Income       total
```

## Split rule examples

### Partial Sale

| Side | Account Source | Amount Basis |
|---|---|---|
| Debit | Selected Money Account | Paid Amount |
| Debit | Party Receivable COA | Due Amount |
| Credit | Transaction Head COA | Total Amount |

Journal:

```text
Dr Cash/Bank              paid amount
Dr Customer Receivable    due amount
    Cr Sales Income       total amount
```

### Partial Purchase

| Side | Account Source | Amount Basis |
|---|---|---|
| Debit | Transaction Head COA | Total Amount |
| Credit | Selected Money Account | Paid Amount |
| Credit | Party Payable COA | Due Amount |

Journal:

```text
Dr Purchase/Expense/Asset total amount
    Cr Cash/Bank          paid amount
    Cr Supplier Payable   due amount
```

## User flow

1. Go to Accounting Rules.
2. Create or edit a rule using Posting Lines.
3. Use `Paid Amount` and `Due Amount` basis only for rules that should be split.
4. Link the Accounting Rule to a Transaction Head.
5. In Transaction Entry, select the Transaction Head.
6. Paid Amount / Due Date fields appear automatically only when the selected rule uses Paid/Due amount basis.

## Database changes

New table:

```text
accounting_rule_lines
```

Fields:

```text
accounting_rule_id
line_side: debit / credit
account_source: selected_money / head_account / party_receivable / party_payable
amount_basis: total / paid / due
sort_order
```

Existing `accounting_rules.debit_source` and `credit_source` are kept for compatibility. New posting is driven by `accounting_rule_lines`.

## Important files changed

```text
database/migrations/2026_06_19_010000_create_accounting_rule_lines_table.php
app/Models/AccountingRuleLine.php
app/Models/AccountingRule.php
app/Services/Accounting/AccountingRuleService.php
app/Services/Accounting/TransactionSettlementService.php
app/Services/Accounting/JournalBuilder.php
app/Services/Accounting/TransactionPostingService.php
app/Services/Accounting/TransactionUpdateService.php
app/Http/Requests/Accounting/StoreAccountingRuleRequest.php
app/Http/Requests/Accounting/UpdateAccountingRuleRequest.php
app/Http/Requests/Accounting/StoreTransactionRequest.php
app/Http/Requests/Accounting/UpdateTransactionRequest.php
app/Http/Controllers/Accounting/TransactionEntryController.php
resources/views/accounting-rules/index.blade.php
resources/views/transactions/create.blade.php
resources/views/transactions/partials/preview.blade.php
resources/js/pages/accounting-rule.js
resources/js/pages/transaction-entry.js
database/seeders/HisebGhorDemoSeeder.php
```

## Deploy commands

```bash
php artisan migrate --force
npm install
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If your server already has `node_modules`, still run `npm install` when Vite/Rolldown native packages are missing.
