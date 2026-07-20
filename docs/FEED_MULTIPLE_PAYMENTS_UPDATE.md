# Feed Purchase and Sale Multiple Payments

Feed Purchase and Feed Sale now accept up to 10 payment rows. Each row contains only:

- a configured Money Account (Cash, Bank, or Digital), and
- the amount paid or received through that account.

The payment-row total is the authoritative paid/received amount. The existing transaction settlement fields remain compatible:

- no payment rows: `CREDIT` / fully due;
- payment total below the transaction total: `PARTIAL`;
- payment total equal to the transaction total: `CASH` / fully paid or received.

Each payment is stored in `transaction_payments` and receives its own selected-money journal line. Optional per-payment references such as cheque numbers, bank notes, or mobile banking TXN IDs are stored with the payment row. The existing `transactions.money_account_id` keeps the first payment account for compatibility with existing screens and reports.

## Accounting setup impact

No new Chart of Account, Transaction Head, or Accounting Rule is required.

The existing Feed Purchase and Feed Sale heads and their CASH, PARTIAL, and CREDIT rules remain unchanged. Multiple payments expand the existing `selected_money` rule line into one journal line per selected Money Account.

Existing setup must still satisfy these prerequisites:

- every selected Money Account is active and mapped to an active level-3 Asset COA;
- Feed Purchase has active CASH, PARTIAL, and CREDIT rules;
- Feed Sale has active CASH, PARTIAL, and CREDIT rules;
- the CASH and PARTIAL rules contain the existing `selected_money` source;
- supplier payable and customer receivable party mappings remain valid.

## Deployment

Run the database migration and rebuild frontend assets after deploying the code:

```bash
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
```
