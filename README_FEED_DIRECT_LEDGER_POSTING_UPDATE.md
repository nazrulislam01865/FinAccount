# Feed Posting Update — Superseded

The earlier direct-ledger implementation has been replaced by the COA and Transaction Head implementation documented in:

`README_FEED_COA_TRANSACTION_HEAD_POSTING.md`

Feed Purchase now posts through a dedicated head under the existing `PURCHASE` transaction type, and Feed Sale posts through a dedicated head under the existing `SALE` transaction type. Both use head-specific Accounting Rules from the central accounting engine.
