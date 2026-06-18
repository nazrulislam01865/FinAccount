# Form Draft, Button Loader, and Profile Photo Update

## What was added

- A separate `form_drafts` table scoped by company and user.
- Save Draft controls on all accounting data-entry forms:
  - Transaction Entry
  - Company Setup
  - Chart of Accounts
  - Money Accounts
  - Parties
  - Accounting Rules
  - Transaction Heads
  - Business Types
  - Currencies
  - Time Zones
  - Financial Years
  - Transaction Categories
  - Party Types
  - Money Account Types
  - Voucher Numbering
  - User create/edit
  - Role create and Role Matrix
- Automatic draft restoration for the same user and form.
- Separate create and edit drafts, including record-specific edit drafts.
- Draft discard support.
- Successful final save/update automatically removes only the matching draft.
- Validation failures keep the draft.
- Passwords, secrets, request tokens, and file contents are never stored in drafts.
- Drafts are not inserted into business/master/transaction tables, so they cannot appear in lists, dropdowns, rules, transaction entry, journals, balances, or reports.

## Button execution behavior

- Only the clicked button displays a spinner.
- Other action buttons and button-style links are locked during execution.
- AJAX actions unlock controls after completion or error.
- Normal form submissions remain locked until the next page is loaded.
- Safe delete and notification actions use the same shared execution state.

## Profile photo improvement

- Replaced the basic file field with a visible drag-and-drop photo chooser.
- Added current/selected image preview.
- Added a clear Choose Photo button and selected filename display.
- Existing image type and 2 MB server validation remain unchanged.

## Deployment

Run:

```bash
php artisan migrate --force
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
