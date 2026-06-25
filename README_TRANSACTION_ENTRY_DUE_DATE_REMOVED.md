# Transaction Entry Due Date Removed

The **Due Date** field has been removed from the transaction create/edit form.

## Changes

- Removed the Due Date input from `resources/views/transactions/create.blade.php`.
- Removed Due Date handling from the transaction-entry JavaScript preview request.
- Removed Due Date validation from transaction create, update, and journal preview requests.
- Kept the existing nullable `due_date` database column for backward compatibility with historical transactions and invoices.
- New and edited transactions now save `due_date` as `NULL`.

No database migration is required.
