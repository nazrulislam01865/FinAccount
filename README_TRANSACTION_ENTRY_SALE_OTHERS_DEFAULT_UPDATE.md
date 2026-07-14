# Transaction Entry Sale: Others Default + Conditional Feed Form

## What changed
- Added a default `Others` option to **Transaction Entry → Sale → What are you selling?**.
- `Others` uses the normal previous sales flow: amount, received amount, receive account, reference, description, and attachment.
- Fish, Cattle, Vegetables, or any custom active Business Area from Feed Business Area Master Data opens the feed-style flow.
- The feed-style flow shows Customer, Warehouse / Location, Feed Items, Total Amount, Other Charges, and Total Bill.
- Hidden feed fields are disabled when `Others` is selected, so they do not block normal sales submission.
- Backend validation now accepts `others` even though it is not stored in the feed business area master table.

## Deployment
No migration is needed.

Run:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
