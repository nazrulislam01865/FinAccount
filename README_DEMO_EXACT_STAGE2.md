# HisebGhor — Demo-Exact Transaction Register and Chart of Accounts

This update is based on the uploaded Laravel project and follows the supplied HTML demo without adding a hierarchical COA system.

## Implemented pages

### Transaction Register

- Server-side Laravel/MySQL records
- Search by voucher, category, head, money account, party, reference, or description
- Category filter: Sales, Payment, Liability
- CSV export using the same active filters
- Edit transaction
- Updating a transaction recalculates and replaces its journal lines
- Delete transaction
- Deleting removes its journal entry and derived journal lines

### Chart of Accounts

The form contains only the demo fields:

- Code
- Name
- Type
- Normal Balance
- Active

The list contains only the demo columns:

- Code
- Account Name
- Type
- Normal
- Balance
- Status
- Action

No parent account, level, group, ledger, posting permission, description, roll-up, or other additional COA rule is included.

## Safe deletion behavior

Safe delete is preserved: deleting a used COA first shows its dependencies, requires explicit confirmation, clears dependent links, deactivates affected setup records, and marks affected transactions/journals incomplete for repair.

## Routes

- `GET /transactions`
- `GET /transactions/export`
- `GET /transactions/{transaction}/edit`
- `PUT /transactions/{transaction}`
- `DELETE /transactions/{transaction}`
- `GET /chart-of-accounts`
- `GET /chart-of-accounts/create`
- `POST /chart-of-accounts`
- `GET /chart-of-accounts/{chart_of_account}/edit`
- `PUT /chart-of-accounts/{chart_of_account}`
- `DELETE /chart-of-accounts/{chart_of_account}`

## Installation/update

Copy your existing `.env` into this project, then run:

```bash
composer install
npm install --ignore-scripts
php artisan optimize:clear
php artisan migrate
npm run build
composer run dev
```

Do not run `migrate:fresh` if you need to retain existing records.

## Demo login

- Email: `admin@hisebghor.test`
- Password: `password`
