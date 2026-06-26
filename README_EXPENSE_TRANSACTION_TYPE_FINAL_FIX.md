# Expense Transaction Type Final Fix

## Actual cause

The running project could contain mixed internal values for the same core transaction type, for example:

- `accounting_options.value = Expense`
- `transaction_heads.category = EXPENSE`

The Transaction Entry tabs and the Transaction Head edit modal previously compared these values literally. Therefore clicking Expense could fall back to the first transaction type, while editing an expense head could leave the Transaction Type select with no selected option.

The uploaded `Archive.zip` also did not contain the earlier normalization changes, so the mismatch remained in the source being deployed.

## Fixes included

- Core transaction type values are normalized in one support class.
- Transaction category options are exposed to forms using canonical values.
- Transaction Entry resolves the requested tab by normalized value rather than literal database text.
- Expense transaction-head queries accept both canonical and legacy aliases.
- Transaction Head edit data uses the canonical category value.
- The modal select has a frontend normalized-value fallback, so `Expense`, `EXPENSE`, `expense`, and `Expenses` all select the Expense option.
- Form validation accepts a canonical transaction type even while the database still contains a legacy mixed-case value.
- Posting, updating, previewing, rule matching, and register filtering use legacy-compatible category matching.
- A new migration reruns the canonical data repair even if an earlier repair migration was already marked as executed.

## New repair migration

`2026_06_26_180000_force_normalize_core_transaction_type_values.php`

## Required deployment steps

Build files are already included in `public/build`. On the server run migrations, clear caches, rebuild caches, and restart PHP-FPM so OPcache cannot keep the old PHP classes.
