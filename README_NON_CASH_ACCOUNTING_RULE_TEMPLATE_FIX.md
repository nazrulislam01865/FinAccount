# Non-Cash Accounting Rule Template Fix

## Problem fixed

The Accounting Rules form rejected Non-Cash transaction types with this validation message:

> Non-Cash transaction types need a dedicated accounting template and are not treated as Money Out automatically.

That happened because the rule generator had templates for Money In, Money Out, and Transfer flows, but Non-Cash flow always threw a validation exception even when the user selected a transaction-head scope such as `Bad Debt Write-off`.

## What changed

- Added a dedicated Non-Cash accounting rule template path.
- Non-Cash accounting rules now require a transaction-head scope.
- Non-Cash rules only support the `CASH` settlement code, shown in the UI as `Fully paid/received`, because no cash/bank/mobile account moves and no due split is created.
- Non-Cash rules do not require a money account.
- Non-Cash rules use the selected transaction head's posting COA plus the party receivable/payable ledger.
- Customer-style Non-Cash heads post against Party Receivable.
- Supplier/Worker/Owner/Lender/custom payable-style Non-Cash heads post against Party Payable.
- If the head is still set to `Any`, the system can infer `Customer` for clear bad-debt/write-off/receivable heads.
- Existing Non-Cash and Transfer transaction-type metadata is normalized by migration so their allowed payment type is only `CASH`.

## Example: Bad Debt Write-off

For a `General Adjustment` / Non-Cash transaction head named `Bad Debt Write-off` linked to an Expense COA:

- Debit: Transaction Head COA
- Credit: Party Receivable COA
- Party required: Customer
- Money account required: No

## Deployment

Run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Changed files

- `app/Services/Accounting/AccountingRuleService.php`
- `app/Support/TransactionTypes.php`
- `app/Services/Accounting/MasterDataService.php`
- `app/Models/TransactionHead.php`
- `database/migrations/2026_07_14_020000_normalize_non_cash_transaction_templates.php`
