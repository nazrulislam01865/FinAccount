# Visible Draft Rows Update

This update changes the draft behavior so saved drafts are visible in their related module list tables as `Draft` rows while remaining unusable by the accounting engine until the user performs the final Save action.

## Key points

- Drafts still live in `form_drafts`, not in operational accounting tables.
- List pages render those draft records as visible rows with a purple `Draft` badge.
- Draft rows have Continue and Discard actions.
- Create drafts reopen the related add form/modal.
- Edit drafts can reopen the original edit form where an original record is still available.
- Final Save continues to create/update the real business record and clears that form draft.
- Drafts are not loaded by operational services, dropdowns, posting rules, transaction previews, journal posting, reports, balances, or statements.

## Affected list pages

- Transaction Register
- Chart of Accounts
- Money Accounts
- Parties
- Accounting Rules
- Transaction Heads
- Voucher Numbering
- Transaction Categories
- Party Types
- Money Account Types
- Business Types
- Currencies
- Time Zones
- Financial Years
- Users
- Role Matrix role overview

## Validation

- PHP syntax validation passed for application PHP files.
- Blade templates were compiled with Laravel's Blade compiler and the compiled PHP passed syntax validation.
- JavaScript syntax validation passed.
- Vite production build completed successfully.
