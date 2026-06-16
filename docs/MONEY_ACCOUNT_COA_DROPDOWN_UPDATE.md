# Money Account COA Dropdown Update

## Changes

- The **Mapped Asset COA** dropdown is loaded directly from the `chart_of_accounts` table.
- Only Asset COA records belonging to the logged-in user's company and marked active are available.
- Store and update validation now rejects COA IDs that are not active Asset accounts from the same company.
- The accounting service performs the same server-side validation before saving.
- The Money Accounts page description was removed as requested.
- When no eligible Asset COA exists, the form tells the user to create or activate one in Chart of Accounts.

## Unchanged

- Existing Money Account CRUD, safe dependency deletion, Master menu, transaction posting, balances, and database persistence behavior remain unchanged.
