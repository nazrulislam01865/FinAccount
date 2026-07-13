# Transaction Head and Accounting Rule Bulk Actions

## Added

Both **Transaction Heads** and **Accounting Rules** now support selecting multiple saved rows and applying one of these actions:

- Set Active
- Set Inactive
- Delete Permanently
- Clear Selection (frontend selection helper)

The toolbar appears only after at least one row is selected. A master checkbox selects or clears all saved rows currently shown in the table. Unsaved draft rows are intentionally excluded.

## Safety behavior

### Set Active

Activation is atomic: if any selected record is incomplete, no selected record is activated.

Transaction Heads are checked for:

- active Level 3 linked COA belonging to the same company;
- active transaction type;
- valid payment types allowed by the transaction type;
- valid expected party type;
- compatible linked COA account type.

Accounting Rules are checked for:

- active transaction and payment types;
- payment type allowed by the transaction type;
- valid party type;
- complete debit and credit posting lines;
- valid account sources and amount bases;
- no duplicate rule for the same transaction-type/payment-type combination.

### Set Inactive

- Inactive Transaction Heads disappear from new transaction entry.
- Inactive Accounting Rules are ignored by automatic rule matching.
- Existing transactions and journals are not deleted or changed.

### Delete Permanently

Delete uses the existing safe-delete preview modal and explicit confirmation.

- Deleting Transaction Heads clears their links from dependent transactions and marks those transactions/journals incomplete.
- Deleting Accounting Rules also removes their generated rule lines. Any legacy Transaction Head links are cleared and those heads are made inactive.
- All selected records are company-scoped and the entire operation is transactional.
- The permanent-delete option is available only to users with the accounting record-delete permission.

## Deployment

No database migration is required.

```bash
php artisan optimize:clear
```

Production Vite assets are already rebuilt in `public/build`.
