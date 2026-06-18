# HisebGhor Exact Menu Flow Update

The accounting sidebar now follows this exact order:

- Dashboard
- Transactions
  - Transaction Entry
  - Transaction Register
  - Journal Entries
- Reports
  - Account Balances
  - Party Balances
  - Income Statement
  - Balance Sheet
  - Cash Flow Statement
- Configuration
  - Chart of Accounts
  - Accounting Rules
  - Transaction Heads
  - Transaction Categories
  - Voucher Numbering
  - Party Types
  - Parties
  - Money Account Types
  - Money Accounts
  - Other Master Data

Implementation details:

- Removed the previous nested Master Data dropdown.
- Added direct links for every menu item.
- Added independent active-state handling for each balance and financial statement link.
- Added report section anchors.
- Added a working Other Master Data overview route and page.
- Preserved existing routes and functionality for backward compatibility.
