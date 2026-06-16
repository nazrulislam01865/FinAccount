# HisebGhor Master Menu Update

The **Master** sidebar menu contains only master-data submenus. This update changes only the two transaction-configuration submenus requested below.

## Transaction Categories

- A new **Add Transaction Category** button is available.
- New categories are stored in `accounting_options` and immediately feed:
  - Accounting Rules
  - Transaction Heads
  - Transaction Entry tabs
  - Transaction Register filters
  - Voucher Numbering category selection
- Each category contains:
  - Internal value
  - Display label
  - Money-field label
  - Sort order
  - Active/inactive status
- Custom unused categories can be edited or deleted.
- Categories already used by rules, heads, or transactions cannot be renamed, deactivated, or deleted.
- Core template categories `Sales`, `Payment`, and `Liability` remain protected from rename, deactivation, and deletion so existing template reports remain correct.

## Voucher Numbering

- A new **Add Voucher Numbering** button is available when an active transaction category does not yet have a numbering setup for the current company.
- The user selects an unconfigured transaction category and enters:
  - Prefix
  - Next number
  - Number length/padding
- One numbering setup is allowed per company and transaction category.
- Prefixes must be unique inside the company.
- The next number cannot be moved backward.
- Existing vouchers and journal entries are never renamed.
- A transaction now shows a clear validation message when its category has no voucher-numbering setup instead of silently creating one.

## Required setup order for a new category

1. Open **Master → Transaction Categories** and add the category.
2. Open **Master → Voucher Numbering** and add its prefix and sequence.
3. Create an Accounting Rule for that category.
4. Create a Transaction Head linked to the rule.
5. The category is then ready for Transaction Entry.
