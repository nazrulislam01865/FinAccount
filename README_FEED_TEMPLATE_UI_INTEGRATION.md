# Feed Purchase, Sale and Inventory Template UI Integration

The supplied feed prototype structure is used for Feed Purchase, Feed Sale and Feed Inventory while preserving the Laravel stock, accounting, authorization and reporting architecture.

## Updated screens

- Feed Purchase
- Feed Sale
- Feed Inventory
- Feed Setup
- Feed module navigation tabs

## Posting logic

Feed Purchase and Feed Sale now post directly to the main ledger through dedicated internal feed heads. The former manual Accounting Connection section has been removed.

The implementation retains:

- Full, partial and fully due payment detection
- Supplier Payable and Customer Receivable mappings
- Money Accounts
- Financial-period validation and voucher sequences
- Sales invoices and attachments
- Weighted-average inventory valuation
- Negative-stock prevention
- COGS posting
- Transaction Register, Journal Entries and financial reports
- Safe stock reversal when deleting eligible feed transactions

Internal feed heads are excluded from generic Transaction Entry, preventing a user from posting a Feed Purchase or Feed Sale journal without updating stock.

## Intentional differences from the prototype

Business Tracking/Allocation controls are not displayed because the current database does not implement that accounting dimension. Purchase Return and Sale Return require dedicated reversal and stock-cost logic and are not represented as working feed posting modes.

## Runtime path protection

`bootstrap/app.php` creates Laravel's required runtime directories before booting, including `storage/framework/views`, preventing the `Please provide a valid cache path` error after ZIP or Git deployment.

## Deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The production Vite assets are included.
