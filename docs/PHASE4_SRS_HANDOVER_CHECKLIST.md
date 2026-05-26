# Phase 4 SRS Handover Checklist

Use this checklist after deploying the Phase 4 update.

## Required deployment commands

```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan srs:phase4-check
```

## Security and RBAC checks

- Log in as Super Admin and verify all modules are available.
- Log in as Company Admin and verify setup, user, transaction, report, approval, and audit access.
- Log in as Accountant and verify CoA, rules, opening balance, manual journal, transaction, and report access.
- Log in as Data Entry Operator and verify setup pages are blocked and draft/create transaction access follows configuration.
- Log in as Manager / Approver and verify approval dashboard and assigned reports.
- Log in as Auditor / Viewer and verify view-only reports, transactions, and audit trail.
- Log in as Business Owner and verify dashboard/report/transaction view access only.
- Confirm protected full-access roles cannot be downgraded accidentally through the role matrix.

## Audit checks

- Update Company Setup and confirm an audit log row is created.
- Update Financial Year status/lock/current year and confirm audit logging.
- Update CoA, Transaction Head, Accounting Rule, Voucher Numbering, Opening Balance, and confirm audit logging.
- Create/update/delete a user and confirm SecurityAccess audit rows.
- Change Role Access Matrix and confirm ROLE_PERMISSION_MATRIX_UPDATED audit row.
- Approve/reject a voucher and confirm approval logs and audit logs.
- Reverse a posted voucher and confirm original and reversal remain visible.

## Approval checks

- Create a transaction below the approval threshold and verify auto-posting behavior.
- Create a transaction above the threshold and verify Pending Review status.
- Approve the transaction and verify status becomes Posted and journal lines are available.
- Reject the transaction and verify status becomes Cancelled/Rejected and reports exclude it.

## API checks

- `GET /api/accounts`
- `POST /api/accounts`
- `GET /api/voucher-numbering`
- `GET /api/transaction-purposes`
- `POST /api/accounting-rules`
- `POST /api/opening-balances/post`
- `POST /api/transactions/post`
- `POST /api/manual-journals/post`
- `GET /api/vouchers`
- `GET /api/journal-entries`
- `GET /api/reports/general-ledger`
- `GET /api/reports/trial-balance`
- `GET /api/reports/profit-loss`
- `GET /api/reports/balance-sheet`
- `GET /api/reports/customer-due`
- `GET /api/reports/supplier-due`

## Final accounting dataset check

Use the PRD fixed test dataset and verify:

- Trial Balance debit total equals credit total.
- Profit & Loss net profit matches expected output.
- Balance Sheet balances.
- Cash/Bank Book matches cash and bank ledger movement.
- Customer due equals Accounts Receivable debit minus credit by party.
- Supplier due equals Accounts Payable credit minus debit by party.
- Posted vouchers cannot be edited directly.
- Reversal creates opposite journal lines and does not reuse the source voucher number.
