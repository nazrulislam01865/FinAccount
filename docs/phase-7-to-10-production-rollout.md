# HisebGhor Phase 7 to 10 Production Rollout Notes

## Phase 7 - Dashboard and UX performance
- Dashboard values are generated from posted voucher lines, not temporary screen values.
- Cash, bank, receivable, payable, monthly income, monthly expense, net profit/loss, setup completion, pending approvals, and recent transactions are cached for five minutes per company/user/day.
- Quick actions keep the existing HisebGhor design while giving users a faster path to setup, posting, and reports.

## Phase 8 - Approval, RBAC, and Audit
- Vouchers can now move through Draft, Submitted/Pending Review, Approved, Posted, Rejected/Cancelled, and Reversed lifecycle states.
- Approval workflows support company, transaction type, transaction head, threshold, approval level, approver role, and active/inactive status.
- Approval logs preserve every submit, approve, and reject action.
- Audit trail coverage is extended through observers and the reusable audit service for setup, vouchers, users, roles, and permissions.

## Phase 9 - Production hardening and Backup readiness
- A health endpoint checks application, database, storage writability, queue driver, environment, and debug mode.
- Inactive-session timeout is configurable through `SESSION_INACTIVE_TIMEOUT`.
- Voucher attachments are stored on the private local disk instead of the public disk.
- Backup readiness is included through scheduled commands for database and file backups.

## Phase 10 - QA, UAT, and rollout
- Contract tests verify dashboard source of truth, approval workflow wiring, audit wiring, session timeout, private attachment storage, backup commands, and production routes.
- UAT should include: setup completion, rule-based posting, approval threshold, approval rejection, audit trail review, report export, attachment upload, backup command, and health endpoint checks.
- Rollback plan: keep the previous deployment artifact, database backup, and storage backup before migration. If deployment fails, restore code, restore database backup, clear caches, and restart queue workers.
