# Correction: Cash/Bank Setup Merge

The previous Cash/Bank package was built from an older intermediate snapshot and unintentionally reverted parts of the latest Party Setup update.

This corrected package uses the newest uploaded Archive.zip as the base and applies only Cash/Bank-related changes.

Preserved from the latest Party update:
- Party Sub Type and Primary Accounting Nature remain hidden from Party Setup.
- PartyAccountingProfile and Capital nature handling remain intact.
- Purpose-based party ledger mappings remain intact.
- Opening balances continue to use party ledger mapping purposes, not free-text subtype inference.
- Safe Party deletion remains intact.

Applied Cash/Bank changes:
- Server-generated immutable IDs (CB-00001, BK-00001, MB-00001).
- Company-scoped records and uniqueness.
- Type-filtered ledger selection.
- Mobile Wallet ledger support.
- Immutable type/linked ledger after accounting history.
- Non-destructive deletion checks.
- Soft-delete and company-safe Cash/Bank Book joins.
- Opening balance preservation during edit.
