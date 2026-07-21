# Unified Invoice / Receipt Layout Fix

Updated the printable document layout to use one shared structure for:

- Due collection and due payment money receipts
- Sales invoices
- General purchase and asset purchase invoices
- Feed sale invoices
- Feed purchase receipts/invoices

Key changes:

- Replaced the overlapping lower section with a normal two-column flow layout.
- Totals and Prepared By are now stacked in the same right column and cannot overlap.
- Long document titles automatically use a smaller font and remain inside the page border.
- Company logo, company name, address, phone, email and website are loaded from Company Setup.
- `www.Bashiragro.com` remains the website fallback.
- Added dense and compact modes for invoices containing more item rows.
- Added strict A4 portrait print rules and one-page clipping protection.
- Removed packaged compiled Blade views so stale receipt markup is not reused.

After deploying, run:

```bash
php artisan optimize:clear
```
